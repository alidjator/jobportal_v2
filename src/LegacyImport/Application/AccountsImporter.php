<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the legacy `users` (job seekers and company owners), except demo accounts. A company owner needs a `companies` row of their own; the email must be a
 * valid address that no account of the new schema (or an earlier row of this run) already uses. A bcrypt password hash is copied, any other value becomes no
 * password; social ids, alert flags and demo accounts are not carried; the account language is `id`; an inactive account becomes `deactivated`.
 */
final readonly class AccountsImporter implements Importer
{
    public const string ENTITY = 'accounts';

    /** The id-map entity under which a legacy user id is remembered. */
    public const string USER = 'user';

    private const int BATCH = 200;

    private const string DEFAULT_CAMPAIGN = 'nosession';

    public function __construct(
        private LegacySource $legacy,
        private AccountWriter $accounts,
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
        $seenEmails = [];

        $this->legacy->chunk('users', self::BATCH, function (array $rows) use ($context, &$seenEmails): void {
            $ids = array_map(static fn (stdClass $row): int => (int) $row->id, $rows);
            $already = $context->idMap->findMany(self::USER, $ids);
            $owners = array_flip(array_map(static fn (stdClass $row): int => (int) $row->user_id, $this->legacy->whereIn('companies', 'user_id', $ids, ['user_id'])));
            $campaigns = [];

            foreach ($this->legacy->whereIn('candidates', 'user_id', $ids, ['user_id', 'campaign_channel']) as $candidate) {
                $campaigns[(int) $candidate->user_id] = $candidate->campaign_channel;
            }

            $this->transactions->run(function () use ($rows, $context, $already, $owners, $campaigns, &$seenEmails): void {
                foreach ($rows as $row) {
                    $this->importRow($row, $context, isset($already[(int) $row->id]), isset($owners[(int) $row->id]), $campaigns[(int) $row->id] ?? null, $seenEmails);
                }
            });
        });
    }

    /** @param  array<string, true>  $seenEmails */
    private function importRow(stdClass $row, ImportContext $context, bool $alreadyImported, bool $isOwner, ?string $campaign, array &$seenEmails): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if ($alreadyImported) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $type = match ($row->role) {
            'candidate' => 'job_seeker',
            'company' => 'company_member',
            default => null,
        };

        $reason = match (true) {
            (int) $row->is_demo_field === 1 => 'demo-account',
            $type === null => 'unknown-role',
            $type === 'company_member' && ! $isOwner => 'company-owner-without-company',
            default => null,
        };

        $name = LegacyText::clean((string) $row->name);
        $email = mb_strtolower(trim((string) $row->email));

        $reason ??= match (true) {
            $name === null || mb_strlen($name) > 255 => 'name-missing',
            $email === '' || mb_strlen($email) > 255 => 'email-invalid',
            isset($seenEmails[$email]) || $this->accounts->emailTaken($email) => 'email-taken',
            default => null,
        };

        if ($reason !== null || $type === null || $name === null) {
            $report->skipped(self::ENTITY, $legacyId, $reason ?? 'unknown-role');

            return;
        }

        $seenEmails[$email] = true;

        if (! LegacyText::isBcrypt($row->password)) {
            $report->note(self::ENTITY, 'password-not-bcrypt');
        }

        $createdAt = $this->time($row->created_at, $context);

        if ($context->write) {
            $newId = $this->accounts->insert(new NewAccount(
                $type,
                $name,
                $email,
                LegacyText::isBcrypt($row->password) ? (string) $row->password : null,
                $row->email_verified_at === null ? null : $this->time($row->email_verified_at, $context),
                (int) $row->status === 1 ? 'active' : 'deactivated',
                'id',
                $this->campaignCode($campaign),
                $createdAt,
                $row->updated_at === null ? $createdAt : $this->time($row->updated_at, $context),
                $this->time(null, $context),
            ));

            $context->idMap->remember(self::USER, $legacyId, $newId, $context->clock->now());
        }

        $report->imported(self::ENTITY);
    }

    private function campaignCode(?string $campaign): ?string
    {
        $campaign = LegacyText::clean($campaign);

        return $campaign === null || $campaign === self::DEFAULT_CAMPAIGN ? null : mb_substr($campaign, 0, 64);
    }

    /** A legacy timestamp (stored in the database's time zone, read as UTC like every timestamp of the new schema), or the run's time when it is empty. */
    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
