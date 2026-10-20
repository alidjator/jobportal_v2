<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\QuestionRules;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the legacy `company_questions` as `text` questions of the company's library (no ideal answer, no must-have). The text must have at least 5 characters
 * (a shorter one is skipped; the legacy column holds at most 255, below the new limit of 300). A question that was made for one job (`reuse = 0`) is archived at once, so it does not fill the library but stays attached to its
 * job. The company owner is the creator. Legacy answers are not migrated.
 */
final class QuestionsImporter implements Importer
{
    public const string ENTITY = 'questions';

    /** The id-map entity under which a legacy question id is remembered. */
    public const string QUESTION = 'question';

    private const int BATCH = 200;

    public function __construct(
        private readonly LegacySource $legacy,
        private readonly ScreeningWriter $screening,
        private readonly TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [AccountsImporter::ENTITY, CompaniesImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        $this->legacy->chunk('company_questions', self::BATCH, function (array $rows) use ($context): void {
            $companyIds = array_values(array_unique(array_map(static fn (stdClass $row): int => (int) $row->company_id, $rows)));
            $owners = [];

            foreach ($this->legacy->whereIn('companies', 'id', $companyIds, ['id', 'user_id']) as $company) {
                $owners[(int) $company->id] = (int) $company->user_id;
            }

            $state = [
                'already' => $context->idMap->findMany(self::QUESTION, array_map(static fn (stdClass $row): int => (int) $row->id, $rows)),
                'companies' => $context->idMap->findMany(CompaniesImporter::COMPANY, $companyIds),
                'owners' => $owners,
                'users' => $context->idMap->findMany(AccountsImporter::USER, array_values(array_unique($owners))),
            ];

            $this->transactions->run(function () use ($rows, $context, $state): void {
                foreach ($rows as $row) {
                    $this->importRow($row, $context, $state);
                }
            });
        });
    }

    /** @param  array<string, array<int, int>>  $state */
    private function importRow(stdClass $row, ImportContext $context, array $state): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if (isset($state['already'][$legacyId])) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $companyId = $state['companies'][(int) $row->company_id] ?? null;
        $creator = $state['users'][$state['owners'][(int) $row->company_id] ?? 0] ?? null;
        $text = LegacyText::clean((string) preg_replace('/\s+/u', ' ', (string) $row->title));
        $length = $text === null ? 0 : mb_strlen($text);

        $reason = match (true) {
            $companyId === null => 'company-not-imported',
            $creator === null => 'owner-not-imported',
            $length < QuestionRules::TEXT_MIN => 'text-too-short',
            default => null,
        };

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $legacyId, $reason);

            if ($companyId !== null) {
                $context->summary->company($companyId, 'screening_questions_skipped', $reason);
            }

            return;
        }

        $archive = (int) $row->reuse === 0;

        if ($archive) {
            $report->note(self::ENTITY, 'archived-single-use');
        }

        if ($context->write) {
            $createdAt = $this->time($row->created_at, $context);
            $newId = $this->screening->insertQuestion(
                (int) $companyId,
                (string) $text,
                (int) $creator,
                $archive ? $this->time(null, $context) : null,
                $createdAt,
                $row->updated_at === null ? $createdAt : $this->time($row->updated_at, $context),
            );
            $context->idMap->remember(self::QUESTION, $legacyId, $newId, $context->clock->now());
        }

        $report->imported(self::ENTITY);
    }

    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
