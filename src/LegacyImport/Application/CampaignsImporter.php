<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Campaign\Domain\CampaignType;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the legacy `campaigns` (a company's registration link). The legacy `slug` is the code that `candidates.campaign_channel` holds, so it becomes the
 * campaign code: lower-cased, and with every character outside letters, digits, `_`, `.` and `-` turned into `-` (flagged `code-normalised`; the registration
 * codes of imported accounts are matched in lower case and are not changed). A code that another campaign already has (compared in lower case; the oldest legacy
 * row wins) is skipped. The legacy Indonesian types map to the new ones. The creator is the legacy `created_by` user, or the company owner when that user was
 * not imported. Every imported campaign is active; the legacy `location` and `description` have no counterpart and are not carried. Only for imported companies.
 */
final readonly class CampaignsImporter implements Importer
{
    public const string ENTITY = 'campaigns';

    /** The id-map entity under which a legacy campaign id is remembered. */
    public const string CAMPAIGN = 'campaign';

    private const array TYPES = [
        'job_fair' => CampaignType::JobFair,
        'lembaga_pendidikan' => CampaignType::EducationInstitution,
        'lembaga_pemerintahan' => CampaignType::GovernmentInstitution,
        'lembaga_training' => CampaignType::TrainingInstitution,
        'mitra' => CampaignType::Partner,
        'job_marketplace' => CampaignType::JobMarketplace,
        'sosial_media' => CampaignType::SocialMedia,
    ];

    private const int CODE_MAX = 64;

    private const int NAME_MAX = 150;

    public function __construct(
        private LegacySource $legacy,
        private CampaignWriter $campaigns,
        private TransactionManager $transactions,
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
        $rows = $this->legacy->all('campaigns', ['id', 'company_id', 'title', 'slug', 'campaign_type', 'start_date', 'end_date', 'created_by', 'created_at', 'updated_at']);
        usort($rows, static fn (stdClass $a, stdClass $b): int => (int) $a->id <=> (int) $b->id);

        $companyIds = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): int => (int) $row->company_id, $rows))));
        $owners = [];

        foreach ($this->legacy->whereIn('companies', 'id', $companyIds, ['id', 'user_id']) as $company) {
            $owners[(int) $company->id] = (int) $company->user_id;
        }

        $creators = array_values(array_unique(array_filter([...array_map(static fn (stdClass $row): int => (int) $row->created_by, $rows), ...array_values($owners)])));

        $state = [
            'already' => $context->idMap->findMany(self::CAMPAIGN, array_map(static fn (stdClass $row): int => (int) $row->id, $rows)),
            'companies' => $context->idMap->findMany(CompaniesImporter::COMPANY, $companyIds),
            'owners' => $owners,
            'users' => $context->idMap->findMany(AccountsImporter::USER, $creators),
        ];

        $this->transactions->run(function () use ($rows, $context, $state): void {
            $codes = [];

            foreach ($rows as $row) {
                $this->importRow($row, $context, $state, $codes);
            }
        });
    }

    /**
     * @param  array<string, array<int, int>>  $state
     * @param  array<string, true>  $codes  the codes taken so far in this run
     */
    private function importRow(stdClass $row, ImportContext $context, array $state, array &$codes): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if (isset($state['already'][$legacyId])) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $legacyCompany = (int) $row->company_id;
        $companyId = $state['companies'][$legacyCompany] ?? null;
        $creator = $state['users'][(int) $row->created_by] ?? $state['users'][$state['owners'][$legacyCompany] ?? 0] ?? null;
        $type = self::TYPES[(string) $row->campaign_type] ?? null;
        $name = LegacyText::clean((string) preg_replace('/\s+/u', ' ', (string) $row->title));
        $original = mb_strtolower(trim((string) $row->slug));
        $code = $this->code($original);
        $startsOn = $this->day($row->start_date);
        $endsOn = $this->day($row->end_date);

        $reason = match (true) {
            $legacyCompany === 0 => 'company-missing',
            $companyId === null => 'company-not-imported',
            $creator === null => 'creator-not-imported',
            $type === null => 'type-unknown',
            $name === null => 'name-missing',
            $original === '' => 'slug-missing',
            $code === null => 'slug-unusable',
            $startsOn === false || $endsOn === false => 'date-invalid',
            $startsOn !== null && $endsOn !== null && $startsOn > $endsOn => 'period-invalid',
            isset($codes[$code]) || $this->campaigns->codeTaken($code) => 'code-taken',
            default => null,
        };

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $legacyId, $reason);

            return;
        }

        $codes[$code] = true;

        if ($code !== $original) {
            $report->flagged(self::ENTITY, $legacyId, 'code-normalised');
        }

        if ($context->write) {
            $createdAt = $this->time($row->created_at, $context);
            $newId = $this->campaigns->insert(
                (int) $companyId,
                (string) $code,
                mb_substr((string) $name, 0, self::NAME_MAX),
                $type->value,
                $startsOn === null ? null : $startsOn->format('Y-m-d'),
                $endsOn === null ? null : $endsOn->format('Y-m-d'),
                (int) $creator,
                $createdAt,
                $row->updated_at === null ? $createdAt : $this->time($row->updated_at, $context),
            );
            $context->idMap->remember(self::CAMPAIGN, $legacyId, $newId, $context->clock->now());
        }

        $report->imported(self::ENTITY);
    }

    /** The new code for a lower-cased legacy slug, or null when nothing usable is left. */
    private function code(string $slug): ?string
    {
        $code = trim((string) preg_replace('/[^a-z0-9_.-]+/', '-', $slug), '-');
        $code = rtrim(mb_substr($code, 0, self::CODE_MAX), '-');

        return $code === '' ? null : $code;
    }

    /** @return DateTimeImmutable|false|null null when there is no date, false when the text is not a date */
    private function day(mixed $value): DateTimeImmutable|false|null
    {
        $text = LegacyText::clean($value === null ? null : (string) $value);

        return $text === null ? null : (LegacyDates::day($text) ?? false);
    }

    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
