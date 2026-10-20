<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the candidates a company saved (`bookmark_company`) and their categories (`company_bookmark_categories`) into the talent pool as entries of the `legacy` source: a private list of the
 * company that shows the name, the category and the saved date, and opens contact only when the person can be found or has a consenting application to that company (the rules the pool already
 * has). An entry's category is the legacy `category_id` or, when it has none, the first row of `bookmark_company_category`. A candidate whose account was not imported is skipped and reported; one who
 * cannot be found is kept. Category names keep their legacy text (cut to 50 characters; a name that repeats another of the company in a different case is the same category); a company keeps at most 30
 * categories, and the entries of the excess ones are left uncategorised and counted. The import time is the entry's last change, which the retention of 24 months counts from.
 * `docs/design/phase-8-activation.md` section 9.
 */
final readonly class SavedCandidatesImporter implements Importer
{
    public const string ENTITY = 'saved-candidates';

    /** The id-map entities: a legacy `company_bookmark_categories` id, and a legacy `bookmark_company` id. */
    public const string CATEGORY = 'talent_category';

    public const string SAVED_CANDIDATE = 'saved_candidate';

    private const int BATCH = 200;

    private const int NAME_MAX = 50;

    private const int MAX_CATEGORIES = 30;

    public function __construct(
        private LegacySource $legacy,
        private TalentPoolWriter $writer,
        private TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [AccountsImporter::ENTITY, CompaniesImporter::ENTITY, ApplicationsImporter::ENTITY, JobAlertsImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        $categories = $this->importCategories($context);

        /** @var array<int, int> $pivot the first category (lowest pivot id) of each legacy bookmark, for a bookmark with no `category_id` of its own */
        $pivot = [];

        foreach ($this->legacy->all('bookmark_company_category', ['id', 'bookmark_id', 'category_id']) as $row) {
            $pivot[(int) $row->bookmark_id] ??= (int) $row->category_id;
        }

        /** @var array<string, true> $seen the (company, candidate) pairs saved so far in this run */
        $seen = [];

        $this->legacy->chunk('bookmark_company', self::BATCH, function (array $rows) use ($context, $categories, $pivot, &$seen): void {
            $candidateIds = array_values(array_unique(array_map(static fn (stdClass $row): int => (int) $row->candidate_id, $rows)));
            $owners = [];

            foreach ($this->legacy->whereIn('candidates', 'id', $candidateIds, ['id', 'user_id']) as $candidate) {
                $owners[(int) $candidate->id] = (int) $candidate->user_id;
            }

            $users = $context->idMap->findMany(AccountsImporter::USER, array_values($owners));
            $companies = $context->idMap->findMany(CompaniesImporter::COMPANY, array_values(array_unique(array_map(static fn (stdClass $row): int => (int) $row->company_id, $rows))));
            $already = $context->idMap->findMany(self::SAVED_CANDIDATE, array_map(static fn (stdClass $row): int => (int) $row->id, $rows));

            $this->transactions->run(function () use ($rows, $context, $owners, $users, $companies, $already, $categories, $pivot, &$seen): void {
                foreach ($rows as $row) {
                    $legacyCategory = $row->category_id === null ? ($pivot[(int) $row->id] ?? null) : (int) $row->category_id;
                    $userId = isset($owners[(int) $row->candidate_id]) ? ($users[$owners[(int) $row->candidate_id]] ?? null) : null;

                    $this->importRow($row, $companies[(int) $row->company_id] ?? null, $userId, $legacyCategory, $categories, isset($already[(int) $row->id]), $context, $seen);
                }
            });
        });
    }

    /**
     * Creates the categories of the companies that were imported and returns the new id of each legacy category that has one.
     *
     * @return array<int, int> by legacy category id (a dry run has no new ids: the legacy ids that would be created map to 0)
     */
    private function importCategories(ImportContext $context): array
    {
        $rows = $this->legacy->all('company_bookmark_categories', ['id', 'company_id', 'name']);
        usort($rows, static fn (stdClass $a, stdClass $b): int => (int) $a->id <=> (int) $b->id);

        $companies = $context->idMap->findMany(CompaniesImporter::COMPANY, array_values(array_unique(array_map(static fn (stdClass $row): int => (int) $row->company_id, $rows))));
        $mapped = $context->idMap->findMany(self::CATEGORY, array_map(static fn (stdClass $row): int => (int) $row->id, $rows));

        /** @var array<int, int> $counts */
        $counts = [];
        /** @var array<string, int> $byName the new id of a category by company and lower-case name, for the ones made in this run */
        $byName = [];
        $result = $mapped;

        $this->transactions->run(function () use ($rows, $companies, $mapped, $context, &$counts, &$byName, &$result): void {
            foreach ($rows as $row) {
                $legacyId = (int) $row->id;
                $context->report->read('saved-candidate-categories');

                if (isset($mapped[$legacyId])) {
                    $context->report->note('saved-candidate-categories', 'already-imported');

                    continue;
                }

                $companyId = $companies[(int) $row->company_id] ?? null;
                $name = mb_substr(trim((string) LegacyText::clean($row->name)), 0, self::NAME_MAX);

                if ($companyId === null) {
                    $context->report->skipped('saved-candidate-categories', $legacyId, 'company-not-imported');

                    continue;
                }

                if ($name === '') {
                    $context->report->skipped('saved-candidate-categories', $legacyId, 'name-empty');

                    continue;
                }

                $key = $companyId.':'.mb_strtolower($name);
                $existing = $byName[$key] ?? ($context->write ? $this->writer->findCategory($companyId, $name) : null);

                if ($existing !== null) {
                    $result[$legacyId] = $existing;
                    $this->remember($context, $legacyId, $existing);
                    $context->report->note('saved-candidate-categories', 'same-name-merged');

                    continue;
                }

                $counts[$companyId] ??= $context->write ? $this->writer->categoryCount($companyId) : 0;

                if ($counts[$companyId] >= self::MAX_CATEGORIES) {
                    $context->report->skipped('saved-candidate-categories', $legacyId, 'category-limit');

                    continue;
                }

                $counts[$companyId]++;
                $newId = $context->write ? $this->writer->insertCategory($companyId, $name, $context->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')) : 0;
                $byName[$key] = $newId;
                $result[$legacyId] = $newId;
                $this->remember($context, $legacyId, $newId);
                $context->report->imported('saved-candidate-categories');
            }
        });

        return $result;
    }

    private function remember(ImportContext $context, int $legacyId, int $newId): void
    {
        if ($context->write) {
            $context->idMap->remember(self::CATEGORY, $legacyId, $newId, $context->clock->now());
        }
    }

    /**
     * @param  array<int, int>  $categories
     * @param  array<string, true>  $seen
     */
    private function importRow(stdClass $row, ?int $companyId, ?int $userId, ?int $legacyCategory, array $categories, bool $alreadyImported, ImportContext $context, array &$seen): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if ($alreadyImported) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        if ($companyId === null) {
            $report->skipped(self::ENTITY, $legacyId, 'company-not-imported');

            return;
        }

        if ($userId === null) {
            $report->skipped(self::ENTITY, $legacyId, 'candidate-not-imported');
            $context->summary->company($companyId, 'saved_candidates_skipped', 'candidate_not_imported');

            return;
        }

        if (isset($seen["{$companyId}:{$userId}"])) {
            $report->skipped(self::ENTITY, $legacyId, 'duplicate');

            return;
        }

        $seen["{$companyId}:{$userId}"] = true;
        $categoryId = $legacyCategory === null ? null : ($categories[$legacyCategory] ?? null);

        if ($legacyCategory !== null && $categoryId === null) {
            $report->note(self::ENTITY, 'category-not-carried');
            $context->summary->company($companyId, 'saved_candidates_uncategorised');
        }

        if ($context->write) {
            $newId = $this->writer->insertLegacyEntry(
                $companyId, $userId, $categoryId, $this->time($row->created_at, $context), $context->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            );

            if ($newId === null) {
                $report->skipped(self::ENTITY, $legacyId, 'duplicate');

                return;
            }

            $context->idMap->remember(self::SAVED_CANDIDATE, $legacyId, $newId, $context->clock->now());
        }

        $context->summary->company($companyId, 'saved_candidates_imported');
        $context->summary->jobSeeker($userId, 'saved_by_companies');
        $report->imported(self::ENTITY);
    }

    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
