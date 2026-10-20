<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the legacy `jobs` with their skills and benefits; the creator and the only assignee is the owner of the company. A row that waits for the approval of
 * an edit (`parent_job_id` set) is not a job of its own and is skipped. A job that applies by email or URL becomes `closed`; otherwise `pending` becomes
 * `pending_review`, `active` stays `active` (`expired` when its deadline has passed) and `expired` stays `expired`. A job that would be live or in review but
 * fails the publish-required list of the new rules becomes a `draft` (a `closed` one stays closed); the first failing rule is flagged by legacy id. Masters go
 * through the master map; the description (HTML) becomes Markdown with the hard skills, soft skills and notes appended as sections; the hard and soft skill
 * text is also matched to the skill master. The legacy tags, the benefits text, featured and highlight promotion and the apply email and URL are not carried.
 */
final class JobsImporter implements Importer
{
    public const string ENTITY = 'jobs';

    /** The id-map entity under which a legacy job id is remembered. */
    public const string JOB = 'job';

    private const int BATCH = 100;

    private const int TITLE_MAX = 150;

    private const int TITLE_MIN = 3;

    private const int DESCRIPTION_MAX = 20000;

    private const int DESCRIPTION_MIN = 50;

    /** The legacy column of each master the job points at => the master key. */
    private const array MASTER_COLUMNS = [
        'category_id' => 'job_categories',
        'role_id' => 'job_roles',
        'job_type_id' => 'job_types',
        'job_level_id' => 'job_levels',
        'work_time' => 'work_times',
        'experience_id' => 'experience_levels',
        'education_id' => 'education_levels',
        'salary_type_id' => 'salary_periods',
    ];

    public function __construct(
        private readonly LegacySource $legacy,
        private readonly JobWriter $jobs,
        private readonly LocationLookup $locations,
        private readonly SkillTextMatcher $skillText,
        private readonly TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [MastersImporter::ENTITY, AccountsImporter::ENTITY, CompaniesImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        $this->legacy->chunk('jobs', self::BATCH, function (array $rows) use ($context): void {
            $state = $this->state($context, $rows);

            $this->transactions->run(function () use ($rows, $context, $state): void {
                foreach ($rows as $row) {
                    $this->importRow($row, $context, $state);
                }
            });
        });
    }

    /**
     * Everything a batch needs, looked up once.
     *
     * @param  list<stdClass>  $rows
     * @return array<string, mixed>
     */
    private function state(ImportContext $context, array $rows): array
    {
        $jobIds = array_map(static fn (stdClass $row): int => (int) $row->id, $rows);
        $companyIds = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->company_id === null ? null : (int) $row->company_id, $rows))));

        $owners = [];

        foreach ($this->legacy->whereIn('companies', 'id', $companyIds, ['id', 'user_id']) as $company) {
            $owners[(int) $company->id] = (int) $company->user_id;
        }

        $masters = [];

        foreach (self::MASTER_COLUMNS as $column => $master) {
            $ids = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->{$column} === null ? null : (int) $row->{$column}, $rows))));
            $masters[$column] = $context->idMap->findMany(MasterCatalog::entityFor($master), $ids);
        }

        $skillLinks = $this->links('job_skills', 'skill_id', $jobIds);
        $benefitLinks = $this->links('job_benefit', 'benefit_id', $jobIds);

        return [
            'already' => $context->idMap->findMany(self::JOB, $jobIds),
            'companies' => $context->idMap->findMany(CompaniesImporter::COMPANY, $companyIds),
            'owners' => $owners,
            'users' => $context->idMap->findMany(AccountsImporter::USER, array_values(array_unique($owners))),
            'masters' => $masters,
            'skillLinks' => $skillLinks,
            'benefitLinks' => $benefitLinks,
            'skills' => $context->idMap->findMany(MasterCatalog::entityFor('skills'), $this->itemIds($skillLinks)),
            'benefits' => $context->idMap->findMany(MasterCatalog::entityFor('benefits'), $this->itemIds($benefitLinks)),
            'today' => $context->clock->now()->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d'),
        ];
    }

    /**
     * The links of a pivot table by legacy job id: the legacy ids of the linked items, in order, with the rows that lack one counted as `null`.
     *
     * @param  list<int>  $jobIds
     * @return array<int, list<int|null>>
     */
    private function links(string $table, string $column, array $jobIds): array
    {
        $links = [];
        $rows = $this->legacy->whereIn($table, 'job_id', $jobIds, ['id', 'job_id', $column]);
        usort($rows, static fn (stdClass $a, stdClass $b): int => (int) $a->id <=> (int) $b->id);

        foreach ($rows as $row) {
            $links[(int) $row->job_id][] = $row->{$column} === null ? null : (int) $row->{$column};
        }

        return $links;
    }

    /**
     * @param  array<int, list<int|null>>  $links
     * @return list<int>
     */
    private function itemIds(array $links): array
    {
        return array_values(array_unique(array_filter(array_merge(...array_values($links ?: [[]])))));
    }

    /** @param  array<string, mixed>  $state */
    private function importRow(stdClass $row, ImportContext $context, array $state): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if (isset($state['already'][$legacyId])) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $companyLegacyId = $row->company_id === null ? null : (int) $row->company_id;
        $companyId = $companyLegacyId === null ? null : ($state['companies'][$companyLegacyId] ?? null);
        $creator = $companyLegacyId === null ? null : ($state['users'][$state['owners'][$companyLegacyId] ?? 0] ?? null);
        $title = LegacyText::clean($row->title);

        $reason = match (true) {
            $row->parent_job_id !== null && (int) $row->parent_job_id !== 0 => 'pending-edit-skipped',
            $companyLegacyId === null => 'company-missing',
            $companyId === null => 'company-not-imported',
            $creator === null => 'owner-not-imported',
            $title === null => 'title-missing',
            mb_strlen($title) < self::TITLE_MIN => 'title-too-short',
            default => null,
        };

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $legacyId, $reason);

            if ($companyId !== null && $reason === 'pending-edit-skipped') {
                $context->summary->company($companyId, 'pending_edits_skipped');
            }

            return;
        }

        if (mb_strlen((string) $title) > self::TITLE_MAX) {
            $report->note(self::ENTITY, 'title-cut');
        }

        $job = $this->map($row, (int) $companyId, (int) $creator, (string) $title, $context, $state);

        if ($context->write) {
            $context->idMap->remember(self::JOB, $legacyId, $this->jobs->insert($job), $context->clock->now());
        }

        $context->summary->company((int) $companyId, 'jobs_imported', $job->status);
        $report->imported(self::ENTITY);
    }

    /** @param  array<string, mixed>  $state */
    private function map(stdClass $row, int $companyId, int $creator, string $title, ImportContext $context, array $state): NewJob
    {
        $legacyId = (int) $row->id;
        $report = $context->report;

        $ids = [];

        foreach (self::MASTER_COLUMNS as $column => $master) {
            $ids[$column] = $row->{$column} === null ? null : ($state['masters'][$column][(int) $row->{$column}] ?? null);

            if ($row->{$column} !== null && $ids[$column] === null) {
                $report->note(self::ENTITY, $master.'-unmatched');
            }
        }

        $description = $this->description($row, $report);
        $vacancies = LegacyVacancies::parse($row->vacancies);

        if (is_string($vacancies)) {
            $report->note(self::ENTITY, $vacancies);
            $vacancies = null;
        }

        $deadline = $this->deadline($row->deadline);
        [$salaryMin, $salaryMax, $salaryPeriod] = $this->salary($row, $ids['salary_type_id'], $legacyId, $report);
        $workMode = (int) $row->is_remote === 1 ? 'remote' : 'onsite';
        $location = $this->location($row, $report);
        $skillIds = $this->skills($row, $state, $report);
        $benefitIds = $this->benefits($legacyId, $state, $report);

        $createdAt = $this->time($row->created_at, $context);
        $updatedAt = $row->updated_at === null ? $createdAt : $this->time($row->updated_at, $context);

        $legacyStatus = (string) ($row->status ?? 'pending');
        $appliesOutside = in_array($row->apply_on, ['email', 'custom_url'], true);
        $status = $this->status($legacyStatus, $appliesOutside, $deadline, $state['today']);

        if ($appliesOutside && $status === 'closed') {
            $context->summary->company($companyId, 'jobs_closed_apply_outside');
        }

        if ($status === 'expired' && $legacyStatus === 'active' && ! $appliesOutside) {
            $report->flagged(self::ENTITY, $legacyId, 'deadline-passed-expired');
        }

        $gap = $this->firstGap($status, $description, $ids, $vacancies, $deadline, $skillIds, $workMode, $location, $state['today']);

        if ($gap !== null) {
            if ($status === 'closed') {
                $report->flagged(self::ENTITY, $legacyId, 'closed-incomplete-'.$gap);
            } else {
                $status = 'draft';
                $report->flagged(self::ENTITY, $legacyId, 'draft-incomplete-'.$gap);
                $context->summary->company($companyId, 'jobs_draft_incomplete', $gap);
            }
        }

        $wasLive = in_array($legacyStatus, ['active', 'expired'], true);

        return new NewJob(
            $companyId,
            $creator,
            LegacyText::limit($title, self::TITLE_MAX) ?? $title,
            SlugMaker::from($title, 100) ?: 'job',
            $status,
            $description,
            $ids['category_id'],
            $ids['role_id'],
            $ids['job_type_id'],
            $ids['job_level_id'],
            $ids['work_time'],
            $ids['experience_id'],
            $ids['education_id'],
            $vacancies,
            $deadline,
            $salaryMin,
            $salaryMax,
            $salaryPeriod,
            $row->show_salary === '1' || $row->show_salary === 1,
            $workMode,
            $location,
            LegacyText::limit(LegacyText::clean($row->address), 255),
            $skillIds,
            $benefitIds,
            $wasLive && in_array($status, ['active', 'expired', 'closed'], true) ? $createdAt : null,
            $createdAt,
            $updatedAt,
        );
    }

    /** The description as Markdown with the filled hard skills, soft skills and notes appended as sections (cut to the limit). */
    private function description(stdClass $row, ImportReport $report): ?string
    {
        $parts = [];
        $main = LegacyMarkdown::from($row->description);

        if ($main !== null) {
            $parts[] = $main;
        }

        foreach (['hard_skill' => 'Hard skills', 'soft_skill' => 'Soft skills', 'notes' => 'Notes'] as $column => $heading) {
            $section = LegacyMarkdown::from($row->{$column});

            if ($section !== null) {
                $parts[] = "## {$heading}\n\n{$section}";
            }
        }

        if ($parts === []) {
            return null;
        }

        $text = implode("\n\n", $parts);

        if (mb_strlen($text) > self::DESCRIPTION_MAX) {
            $report->note(self::ENTITY, 'description-cut');
            $text = mb_substr($text, 0, self::DESCRIPTION_MAX);
        }

        return $text;
    }

    /** The legacy deadline as `Y-m-d`, or null when it is empty or unreadable. */
    private function deadline(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $value, 0, 10), new DateTimeZone('UTC'));

        return $date === false || $date->format('Y-m-d') !== substr((string) $value, 0, 10) ? null : $date->format('Y-m-d');
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: ?int} the minimum, the maximum and the period; all empty when there is no usable range salary
     */
    private function salary(stdClass $row, ?int $period, int $legacyId, ImportReport $report): array
    {
        if ($row->salary_mode !== null && $row->salary_mode !== 'range') {
            return [null, null, null];
        }

        $min = (int) $row->min_salary > 0 ? (int) $row->min_salary : null;
        $max = (int) $row->max_salary > 0 ? (int) $row->max_salary : null;

        if ($min === null && $max === null) {
            return [null, null, null];
        }

        if ($period === null) {
            $report->note(self::ENTITY, 'salary-period-unmatched');

            return [null, null, null];
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
            $report->flagged(self::ENTITY, $legacyId, 'salary-swapped');
        }

        return [$min, $max, $period];
    }

    /** The web columns first (province, city, subdistrict); the old API columns when the web ones give no province. A village or postal code needs a village, which legacy jobs do not have. */
    private function location(stdClass $row, ImportReport $report): ResolvedLocation
    {
        $location = $this->locations->resolve($this->text($row->region), $this->text($row->district), $this->text($row->subdistrict), null, null);

        if (! $location->isEmpty()) {
            return $location;
        }

        $fallback = $this->locations->resolve($this->text($row->province_id), $this->text($row->city_id), null, null, null);

        if (! $fallback->isEmpty()) {
            $report->note(self::ENTITY, 'location-fallback');
        }

        return $fallback;
    }

    /**
     * The master skills of a job: the legacy links first, then the items matched in the hard and soft skill text (no duplicates, at most 15).
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function skills(stdClass $row, array $state, ImportReport $report): array
    {
        $ids = [];

        foreach ($state['skillLinks'][(int) $row->id] ?? [] as $legacySkill) {
            $new = $legacySkill === null ? null : ($state['skills'][$legacySkill] ?? null);

            if ($new === null) {
                $report->note(self::ENTITY, $legacySkill === null ? 'skill-link-incomplete' : 'skill-unmatched');

                continue;
            }

            $ids[] = $new;
        }

        foreach (['hard_skill' => 'hard', 'soft_skill' => 'soft'] as $column => $kind) {
            $found = $this->skillText->match($row->{$column}, $kind);
            $ids = [...$ids, ...$found['matched']];
            $report->note(self::ENTITY, 'skill-text-matched', count($found['matched']));
            $report->note(self::ENTITY, 'skill-text-unmatched', $found['unmatched']);
            $report->note(self::ENTITY, 'skill-text-ambiguous', $found['ambiguous']);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function benefits(int $legacyId, array $state, ImportReport $report): array
    {
        $ids = [];

        foreach ($state['benefitLinks'][$legacyId] ?? [] as $legacyBenefit) {
            $new = $legacyBenefit === null ? null : ($state['benefits'][$legacyBenefit] ?? null);

            if ($new === null) {
                $report->note(self::ENTITY, $legacyBenefit === null ? 'benefit-link-incomplete' : 'benefit-unmatched');

                continue;
            }

            $ids[] = $new;
        }

        return array_values(array_unique($ids));
    }

    private function status(string $legacyStatus, bool $appliesOutside, ?string $deadline, string $today): string
    {
        if ($appliesOutside) {
            return 'closed';
        }

        return match ($legacyStatus) {
            'active' => $deadline !== null && $deadline < $today ? 'expired' : 'active',
            'expired' => 'expired',
            default => 'pending_review',
        };
    }

    /**
     * The first rule of the publish-required list that the job fails, or null. The "future date" test of the deadline applies only to a job in review: a live
     * job whose deadline has passed was already made `expired`, and an expired job only needs a deadline.
     *
     * @param  array<string, int|null>  $ids
     * @param  list<int>  $skillIds
     */
    private function firstGap(string $status, ?string $description, array $ids, ?int $vacancies, ?string $deadline, array $skillIds, string $workMode, ResolvedLocation $location, string $today): ?string
    {
        $checks = [
            'description' => $description !== null && mb_strlen(trim($description)) >= self::DESCRIPTION_MIN,
            'category' => $ids['category_id'] !== null,
            'role' => $ids['role_id'] !== null,
            'job-type' => $ids['job_type_id'] !== null,
            'job-level' => $ids['job_level_id'] !== null,
            'work-time' => $ids['work_time'] !== null,
            'experience-level' => $ids['experience_id'] !== null,
            'education-level' => $ids['education_id'] !== null,
            'vacancies' => $vacancies !== null,
            'deadline' => $deadline !== null,
            'deadline-passed' => $status !== 'pending_review' || $deadline === null || $deadline >= $today,
            'skills' => $skillIds !== [],
            'location' => $workMode === 'remote' || ($location->provinceId !== null && $location->cityId !== null),
        ];

        foreach ($checks as $rule => $passes) {
            if (! $passes) {
                return $rule;
            }
        }

        return null;
    }

    private function text(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
