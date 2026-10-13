<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Applicant\ApplicantProfileSource;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use stdClass;
use Throwable;

/**
 * Imports `applied_jobs` as applications. The only status of a legacy application is its pipeline group, which is mapped to a stage by the group mapping file
 * (`groups`, see `StageGroupMapping`; the five default group names are mapped by name). The snapshot is built from the migrated profile with the same code as a
 * normal application and marked `migrated`; the history has the first entry `applied` and, when the stage is another one, one entry by the system. For one
 * applicant and job the newest application keeps its stage and the unique key, older ones are imported as `withdrawn`. The cover letter is imported whole.
 * Nobody is notified and legacy answers are not migrated. A dry run reads and reports but writes nothing.
 */
final class ApplicationsImporter implements Importer, ValidatesMappings
{
    public const string ENTITY = 'applications';

    /** The id-map entity under which a legacy application id is remembered. */
    public const string APPLICATION = 'application';

    /** The name of the mapping file (`--map=groups=<path>`). */
    public const string MAPPING = 'groups';

    private const int BATCH = 200;

    private const int COVER_LETTER_MAX_BYTES = 60000;

    private const int STAGE_NAME_MAX = 60;

    private const string DIRECTORY = 'recruitment/applications';

    /** @var array<string, array{id: int, type: StageType}> stage by "company:group" */
    private array $stageCache = [];

    /** @var array<int, true> */
    private array $reportedGroups = [];

    public function __construct(
        private readonly LegacySource $legacy,
        private readonly ApplicationWriter $writer,
        private readonly ApplicantProfileSource $profiles,
        private readonly ApplicantContacts $contacts,
        private readonly FileStorage $storage,
        private readonly TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [
            AccountsImporter::ENTITY,
            CompaniesImporter::ENTITY,
            ProfilesImporter::ENTITY,
            ExperiencesImporter::ENTITY,
            EducationsImporter::ENTITY,
            SkillsImporter::ENTITY,
            LanguagesImporter::ENTITY,
            LinksImporter::ENTITY,
            ResumesImporter::ENTITY,
            JobsImporter::ENTITY,
        ];
    }

    public function validateMappings(array $mappings): void
    {
        StageGroupMapping::from($mappings[self::MAPPING] ?? []);
    }

    public function run(ImportContext $context): void
    {
        $mapping = StageGroupMapping::from($context->mapping(self::MAPPING));
        $groups = [];

        foreach ($this->legacy->all('application_groups', ['id', 'name']) as $group) {
            $groups[(int) $group->id] = (string) $group->name;
        }

        $newest = $this->newestApplications();
        $this->stageCache = [];
        $this->reportedGroups = [];

        $this->legacy->chunk('applied_jobs', self::BATCH, function (array $rows) use ($context, $mapping, $groups, $newest): void {
            $state = $this->state($rows, $context);
            $stored = [];

            try {
                $this->transactions->run(function () use ($rows, $context, $mapping, $groups, $newest, $state, &$stored): void {
                    foreach ($rows as $row) {
                        $this->importRow($row, $context, $mapping, $groups, $newest, $state, $stored);
                    }
                });
            } catch (Throwable $e) {
                // The resume copies are written inside the transaction; when anything fails they must not stay behind.
                foreach ($stored as $path) {
                    $this->storage->delete($path);
                }

                throw $e;
            }
        });
    }

    /**
     * The newest legacy application of each applicant and job, found over the whole table so that a batch boundary never splits a duplicate group.
     *
     * @return array<string, int> the legacy id of the newest application by "legacy user id-legacy job id"
     */
    private function newestApplications(): array
    {
        $rows = $this->legacy->all('applied_jobs', ['id', 'candidate_id', 'job_id', 'created_at']);
        $owners = $this->legacyOwners($rows);
        $newest = [];

        foreach ($rows as $row) {
            $user = $owners[(int) $row->candidate_id] ?? null;

            if ($user === null || $row->job_id === null) {
                continue;
            }

            $key = $user.'-'.(int) $row->job_id;
            $current = $newest[$key] ?? null;

            if ($current === null || [(string) $row->created_at, (int) $row->id] > [$current['at'], $current['id']]) {
                $newest[$key] = ['at' => (string) $row->created_at, 'id' => (int) $row->id];
            }
        }

        return array_map(static fn (array $entry): int => $entry['id'], $newest);
    }

    /**
     * @param  list<stdClass>  $rows
     * @return array<int, int> the legacy user id by legacy candidate id
     */
    private function legacyOwners(array $rows): array
    {
        $candidateIds = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->candidate_id === null ? null : (int) $row->candidate_id, $rows))));
        $owners = [];

        foreach ($this->legacy->whereIn('candidates', 'id', $candidateIds, ['id', 'user_id']) as $candidate) {
            $owners[(int) $candidate->id] = (int) $candidate->user_id;
        }

        return $owners;
    }

    /**
     * What a batch needs from the legacy tables and the id map.
     *
     * @param  list<stdClass>  $rows
     * @return array{already: array<int, int>, owners: array<int, int>, users: array<int, int>, jobs: array<int, int>, jobCompanies: array<int, int>, companies: array<int, int>, resumes: array<int, int>}
     */
    private function state(array $rows, ImportContext $context): array
    {
        $owners = $this->legacyOwners($rows);
        $jobIds = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->job_id === null ? null : (int) $row->job_id, $rows))));
        $jobCompanies = [];

        foreach ($this->legacy->whereIn('jobs', 'id', $jobIds, ['id', 'company_id']) as $job) {
            $jobCompanies[(int) $job->id] = (int) $job->company_id;
        }

        $resumeIds = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->candidate_resume_id === null ? null : (int) $row->candidate_resume_id, $rows))));

        return [
            'already' => $context->idMap->findMany(self::APPLICATION, array_map(static fn (stdClass $row): int => (int) $row->id, $rows)),
            'owners' => $owners,
            'users' => $context->idMap->findMany(AccountsImporter::USER, array_values(array_unique($owners))),
            'jobs' => $context->idMap->findMany(JobsImporter::JOB, $jobIds),
            'jobCompanies' => $jobCompanies,
            'companies' => $context->idMap->findMany(CompaniesImporter::COMPANY, array_values(array_unique($jobCompanies))),
            'resumes' => $context->idMap->findMany(ResumesImporter::RESUME, $resumeIds),
        ];
    }

    /**
     * @param  array<int, string>  $groups
     * @param  array<string, int>  $newest
     * @param  array<string, array<int, int>>  $state
     * @param  list<string>  $stored
     */
    private function importRow(stdClass $row, ImportContext $context, StageGroupMapping $mapping, array $groups, array $newest, array $state, array &$stored): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if (isset($state['already'][$legacyId])) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $legacyUser = $row->candidate_id === null ? null : ($state['owners'][(int) $row->candidate_id] ?? null);
        $userId = $legacyUser === null ? null : ($state['users'][$legacyUser] ?? null);
        $jobId = $row->job_id === null ? null : ($state['jobs'][(int) $row->job_id] ?? null);
        $companyId = $row->job_id === null ? null : ($state['companies'][$state['jobCompanies'][(int) $row->job_id] ?? 0] ?? null);

        $reason = match (true) {
            $row->job_id === null => 'job-missing',
            $legacyUser === null => 'candidate-missing',
            $userId === null => 'user-not-imported',
            $jobId === null => 'job-not-imported',
            $companyId === null => 'company-not-imported',
            default => null,
        };

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $legacyId, $reason);

            return;
        }

        $defaults = $this->writer->defaultStages((int) $companyId);

        if (! isset($defaults[StageType::Applied->value], $defaults[StageType::Withdrawn->value])) {
            $report->skipped(self::ENTITY, $legacyId, 'company-without-stages');

            return;
        }

        $isNewest = ($newest[$legacyUser.'-'.(int) $row->job_id] ?? null) === $legacyId;
        $flag = null;

        if ($isNewest && $this->writer->hasLiveApplication((int) $userId, (int) $jobId)) {
            // A live application of the new system holds the unique key already (it never overwrites): this one is kept as history.
            $isNewest = false;
            $flag = 'live-application-exists';
        }

        $stage = $isNewest
            ? $this->stageOf($row, (int) $companyId, $mapping, $groups, $defaults, $context)
            : ['id' => $defaults[StageType::Withdrawn->value], 'type' => StageType::Withdrawn];

        if (! $isNewest && $flag === null) {
            $report->note(self::ENTITY, 'older-duplicate-withdrawn');
        }

        if ($flag !== null) {
            $report->flagged(self::ENTITY, $legacyId, $flag);
        }

        $this->checkRow($row, $context);

        if ($context->write) {
            $this->store($row, (int) $userId, (int) $jobId, (int) $companyId, $stage, $defaults[StageType::Applied->value], $state, $context, $stored);
        }

        $report->imported(self::ENTITY);
    }

    /** Notes about the row that change nothing: legacy answers are not migrated, and a missing resume is reported. */
    private function checkRow(stdClass $row, ImportContext $context): void
    {
        $answers = trim((string) $row->answers);

        if (! in_array($answers, ['', '[]', '{}', 'null'], true)) {
            $context->report->note(self::ENTITY, 'answers-not-migrated');
        }
    }

    /**
     * The stage of the newest application: the mapped stage of its group (an extra stage is created when the mapping asks for it).
     *
     * @param  array<int, string>  $groups
     * @param  array<string, int>  $defaults
     * @return array{id: int, type: StageType}
     */
    private function stageOf(stdClass $row, int $companyId, StageGroupMapping $mapping, array $groups, array $defaults, ImportContext $context): array
    {
        $report = $context->report;
        $groupId = $row->application_group_id === null ? null : (int) $row->application_group_id;
        $name = $groupId === null ? null : ($groups[$groupId] ?? null);
        $target = $groupId === null ? null : $mapping->targetFor($groupId, $name);

        if ($groupId === null || $name === null) {
            $report->note(self::ENTITY, 'group-missing');

            return ['id' => $defaults[StageType::Applied->value], 'type' => StageType::Applied];
        }

        if ($target === null) {
            $report->note(self::ENTITY, 'group-unmapped');

            if (! isset($this->reportedGroups[$groupId])) {
                $this->reportedGroups[$groupId] = true;
                $report->unmatched(self::ENTITY, ['group_id' => $groupId, 'group_name' => $name]);
            }

            return ['id' => $defaults[StageType::Applied->value], 'type' => StageType::Applied];
        }

        $default = $defaults[$target->type->value] ?? null;

        if ($default === null) {
            $report->note(self::ENTITY, 'stage-type-missing');

            return ['id' => $defaults[StageType::Applied->value], 'type' => StageType::Applied];
        }

        if (! $target->createStage) {
            return ['id' => $default, 'type' => $target->type];
        }

        $key = $companyId.':'.$groupId;

        return $this->stageCache[$key] ??= $this->extraStage($companyId, $name, $target->type, $default, $context);
    }

    /**
     * An extra stage named like the group; an existing stage of that name and type is reused, and when the stage cannot be created the default stage of the type is used.
     *
     * @return array{id: int, type: StageType}
     */
    private function extraStage(int $companyId, string $groupName, StageType $type, int $default, ImportContext $context): array
    {
        $report = $context->report;
        $fallback = ['id' => $default, 'type' => $type];
        $name = LegacyText::limit(LegacyText::clean((string) preg_replace('/\s+/u', ' ', $groupName)), self::STAGE_NAME_MAX);

        if ($name === null) {
            $report->note(self::ENTITY, 'stage-name-missing');

            return $fallback;
        }

        $existing = $this->writer->stageByName($companyId, $name);

        if ($existing !== null) {
            if ($existing['type'] === $type->value) {
                return ['id' => $existing['id'], 'type' => $type];
            }

            $report->note(self::ENTITY, 'stage-name-taken');

            return $fallback;
        }

        $report->note(self::ENTITY, 'stage-created');

        if (! $context->write) {
            return $fallback;
        }

        return ['id' => $this->writer->insertStage($companyId, $type->value, $name, $this->time(null, $context)), 'type' => $type];
    }

    /**
     * @param  array{id: int, type: StageType}  $stage
     * @param  array<string, array<int, int>>  $state
     * @param  list<string>  $stored
     */
    private function store(stdClass $row, int $userId, int $jobId, int $companyId, array $stage, int $appliedStageId, array $state, ImportContext $context, array &$stored): void
    {
        $report = $context->report;
        $legacyId = (int) $row->id;
        $now = $this->time(null, $context);
        $appliedAt = $this->time($row->created_at, $context);
        $resumeId = $row->candidate_resume_id === null ? null : ($state['resumes'][(int) $row->candidate_resume_id] ?? null);

        if ($row->candidate_resume_id === null) {
            $report->note(self::ENTITY, 'no-resume');
        } elseif ($resumeId === null) {
            $report->note(self::ENTITY, 'resume-not-migrated');
        }

        $hasProfile = $this->writer->hasProfile($userId);
        $submission = $hasProfile || $resumeId !== null ? $this->profiles->forApplication($userId, $resumeId ?? 0) : null;
        $contact = $this->contacts->contact($userId);

        if ($resumeId !== null && $submission?->resume === null) {
            $report->note(self::ENTITY, 'resume-file-missing');
        }

        $snapshot = [
            'version' => 1,
            'name' => $contact?->name,
            'email' => $contact?->email,
            ...($hasProfile && $submission !== null ? $submission->snapshot : []),
            'migrated' => true,
            'migrated_at' => $context->clock->now()->format(DATE_ATOM),
        ];

        $resume = $submission?->resume;
        $path = null;

        if ($resume !== null) {
            $path = self::DIRECTORY.'/'.bin2hex(random_bytes(20)).'.pdf';
            $this->storage->put($path, $resume->contents);
            $stored[] = $path;
        }

        $coverLetter = LegacyText::clean($row->cover_letter === null ? null : (string) $row->cover_letter);

        if ($coverLetter !== null && strlen($coverLetter) > self::COVER_LETTER_MAX_BYTES) {
            $coverLetter = mb_strcut($coverLetter, 0, self::COVER_LETTER_MAX_BYTES);
            $report->flagged(self::ENTITY, $legacyId, 'cover-letter-cut');
        }

        $isWithdrawn = $stage['type'] === StageType::Withdrawn;
        $id = $this->writer->insertApplication([
            'user_id' => $userId,
            'job_id' => $jobId,
            'company_id' => $companyId,
            'stage_id' => $stage['id'],
            'stage_type' => $stage['type']->value,
            'active_key' => $isWithdrawn ? null : $userId.'-'.$jobId,
            'cover_letter' => $coverLetter,
            'resume_name' => $resume?->name,
            'resume_path' => $path,
            'resume_size_bytes' => $resume === null ? null : strlen($resume->contents),
            'profile_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'meets_must_have' => null,
            'withdrawn_note' => null,
            'applied_at' => $appliedAt,
            'created_at' => $appliedAt,
            'updated_at' => $row->updated_at === null ? $appliedAt : $this->time($row->updated_at, $context),
        ]);

        $this->writer->insertHistory($id, null, $appliedStageId, null, StageType::Applied->value, 'applicant', $userId, $appliedAt);

        if ($stage['type'] !== StageType::Applied) {
            $this->writer->insertHistory($id, $appliedStageId, $stage['id'], StageType::Applied->value, $stage['type']->value, 'system', null, $now);
        }

        $context->idMap->remember(self::APPLICATION, $legacyId, $id, $context->clock->now());
    }

    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
