<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the legacy `emails` (the newsletter list). A legacy row only existed after its confirmation link was clicked, so each address becomes a `confirmed`
 * subscriber: `confirmed_at` is the legacy `created_at` (the import time when it is empty), the language is `id`, and the address is lower-cased. An invalid
 * address and a duplicate (the oldest row wins) are skipped and reported. It needs no other entity.
 */
final readonly class NewsletterSubscribersImporter implements Importer
{
    public const string ENTITY = 'newsletter-subscribers';

    /** The id-map entity under which a legacy `emails` id is remembered. */
    public const string SUBSCRIBER = 'newsletter-subscriber';

    private const string LOCALE = 'id';

    private const int EMAIL_MAX = 254;

    public function __construct(
        private LegacySource $legacy,
        private NewsletterSubscriberWriter $subscribers,
        private TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [];
    }

    public function run(ImportContext $context): void
    {
        $rows = $this->legacy->all('emails', ['id', 'email', 'created_at', 'updated_at']);
        usort($rows, static fn (stdClass $a, stdClass $b): int => (int) $a->id <=> (int) $b->id);

        $already = $context->idMap->findMany(self::SUBSCRIBER, array_map(static fn (stdClass $row): int => (int) $row->id, $rows));

        $this->transactions->run(function () use ($rows, $context, $already): void {
            $seen = [];

            foreach ($rows as $row) {
                $this->importRow($row, $context, isset($already[(int) $row->id]), $seen);
            }
        });
    }

    /** @param  array<string, true>  $seen  the addresses taken so far in this run */
    private function importRow(stdClass $row, ImportContext $context, bool $alreadyImported, array &$seen): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if ($alreadyImported) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $email = mb_strtolower(trim((string) $row->email));

        $reason = match (true) {
            $email === '' => 'email-missing',
            filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > self::EMAIL_MAX => 'email-invalid',
            isset($seen[$email]) || $this->subscribers->emailTaken($email) => 'email-taken',
            default => null,
        };

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $legacyId, $reason);

            return;
        }

        $seen[$email] = true;

        if ($context->write) {
            $confirmedAt = $this->time($row->created_at, $context);
            $newId = $this->subscribers->insertConfirmed($email, self::LOCALE, $confirmedAt, $row->updated_at === null ? $confirmedAt : $this->time($row->updated_at, $context));
            $context->idMap->remember(self::SUBSCRIBER, $legacyId, $newId, $context->clock->now());
        }

        $report->imported(self::ENTITY);
    }

    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
