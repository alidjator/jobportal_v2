<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\LegacyImport\Application\ApplicationWriter;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Src\LegacyImport\Application\StageGroupMapping;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class ApplicationsImporterTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    private const string PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    private int $owner;

    private int $company;

    private int $job;

    private int $user;

    private int $candidate;

    private string $root;

    /** @var array<string, int> legacy group id by name */
    private array $groups = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-08 09:00:00');
        Storage::fake();
        $this->setUpLegacyDatabase();
        $this->locationChain();
        $this->industryId();
        $this->organizationTypeId();
        $this->legacyMasterItem('industry_types', 1, ['en' => 'technology']);
        $this->legacyMasterItem('organization_types', 1, ['en' => 'private-company']);

        $this->root = sys_get_temp_dir().'/legacy-applications-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/storage/app/resumes', 0777, true);
        config(['legacy_import.files_root' => $this->root]);

        $this->owner = $this->legacyUser(['name' => 'PT Maju Jaya', 'role' => 'company']);
        $this->company = $this->legacyCompany($this->owner, 1, 1);
        $this->job = $this->legacyRow('jobs', ['company_id' => $this->company, 'title' => 'Backend Developer', 'status' => 'pending', 'apply_on' => 'app', 'created_at' => '2026-08-01 08:00:00']);
        $this->user = $this->legacyUser(['name' => 'Siti Rahma', 'email' => 'siti@example.test'], ['title' => 'Backend Engineer', 'phone' => '08123456789']);
        $this->candidate = $this->legacyCandidateId($this->user);

        foreach (['No Group', 'All Applications', 'Shortlisted', 'Interview', 'Rejected'] as $order => $name) {
            $this->groups[$name] = $this->group($name, $order);
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function group(string $name, int $order = 9, ?int $company = null): int
    {
        return $this->legacyRow('application_groups', ['company_id' => $company ?? $this->company, 'name' => $name, 'order' => $order]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function application(string $group = 'All Applications', array $attributes = []): int
    {
        return $this->legacyRow('applied_jobs', [
            'candidate_id' => $this->candidate, 'job_id' => $this->job, 'application_group_id' => $this->groups[$group] ?? null,
            'cover_letter' => 'I would love to join.', 'created_at' => '2026-08-10 08:00:00', 'updated_at' => '2026-08-11 09:00:00', ...$attributes,
        ]);
    }

    private function resume(?string $contents = self::PDF): int
    {
        $path = 'resumes/'.bin2hex(random_bytes(4)).'.pdf';

        if ($contents !== null) {
            file_put_contents($this->root.'/storage/app/'.$path, $contents);
        }

        return $this->legacyRow('candidate_resumes', ['candidate_id' => $this->candidate, 'name' => 'cv.pdf', 'file' => $path, 'created_at' => '2026-08-01 08:00:00']);
    }

    /** @param  array<string, array<string, mixed>>  $maps */
    private function import(bool $write = true, array $maps = []): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'companies', 'profiles', 'resumes', 'jobs'] as $step) {
            $runner->run($step, true);
        }

        return $runner->run('applications', $write, $maps)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report): array
    {
        return $report->toArray(true)['entities']['applications'];
    }

    /** @return list<string> */
    private function skipReasons(ImportReport $report): array
    {
        return array_map(static fn (array $row): string => $row['reason'], $this->entity($report)['skipped_rows']);
    }

    private function stageType(int $stageId): string
    {
        return (string) DB::table('recruitment_stages')->where('id', $stageId)->value('type');
    }

    // ----- what is imported -----

    public function test_an_application_is_imported_with_its_cover_letter_times_snapshot_and_history(): void
    {
        $legacy = $this->application('No Group', ['cover_letter' => "  Dear team,\n\nI would love to join.  "]);

        $report = $this->import();

        $row = DB::table('recruitment_applications')->first();
        $userId = (int) DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $this->user)->value('new_id');
        $this->assertSame($userId, (int) $row->user_id);
        $this->assertSame((int) DB::table('job_jobs')->value('id'), (int) $row->job_id);
        $this->assertSame((int) DB::table('company_companies')->value('id'), (int) $row->company_id);
        $this->assertSame(['applied', true], [$row->stage_type, $this->stageType((int) $row->stage_id) === 'applied']);
        $this->assertSame($userId.'-'.$row->job_id, $row->active_key);
        $this->assertSame("Dear team,\n\nI would love to join.", $row->cover_letter);
        $this->assertSame(['2026-08-10 08:00:00', '2026-08-10 08:00:00', '2026-08-11 09:00:00'], [$row->applied_at, $row->created_at, $row->updated_at]);
        $this->assertNull($row->meets_must_have);
        $this->assertNull($row->resume_path);
        $this->assertSame((int) $row->id, (int) DB::table('legacyimport_id_map')->where('entity', 'application')->where('legacy_id', $legacy)->value('new_id'));

        $history = DB::table('recruitment_application_stage_history')->get();
        $this->assertCount(1, $history);
        $this->assertSame([null, 'applied', 'applicant', $userId, '2026-08-10 08:00:00'], [$history[0]->from_type, $history[0]->to_type, $history[0]->actor_kind, (int) $history[0]->actor_id, $history[0]->created_at]);
        $this->assertSame([1, 1, 0], [$this->entity($report)['read'], $this->entity($report)['imported'], $this->entity($report)['skipped']]);
    }

    public function test_the_snapshot_is_built_from_the_migrated_profile_and_marked_migrated(): void
    {
        $this->application();

        $this->import();

        $snapshot = json_decode((string) DB::table('recruitment_applications')->value('profile_snapshot'), true);
        $this->assertSame(['Siti Rahma', 'siti@example.test', 'Backend Engineer', $snapshot['phone'] === '+628123456789' || $snapshot['phone'] === '08123456789'], [$snapshot['name'], $snapshot['email'], $snapshot['headline'], true]);
        $this->assertTrue($snapshot['migrated']);
        $this->assertSame('2026-10-08T09:00:00+00:00', $snapshot['migrated_at']);
        $this->assertArrayNotHasKey('gender', $snapshot);
        $this->assertArrayNotHasKey('birth_date', $snapshot);
    }

    public function test_an_imported_application_keeps_the_job_content_as_of_the_migration(): void
    {
        $this->application();

        $this->import();

        $content = json_decode((string) DB::table('recruitment_applications')->value('job_snapshot'), true);
        $this->assertSame([1, 'Backend Developer', true], [$content['version'], $content['title'], $content['as_of_migration']]);
        $this->assertEqualsCanonicalizing(['version', 'title', 'description', 'work_mode', 'locations', 'salary', 'skills', 'benefits', 'tags', 'as_of_migration'], array_keys($content));
    }

    public function test_an_applicant_without_a_migrated_profile_gets_a_snapshot_with_the_name_and_email_only(): void
    {
        DB::connection('legacy')->table('candidates')->where('id', $this->candidate)->update(['user_id' => $this->user]);
        $other = $this->legacyUser(['name' => 'Budi Santoso', 'email' => 'budi@example.test']);
        $this->application('All Applications', ['candidate_id' => $this->legacyCandidateId($other)]);

        $this->import(maps: []);
        DB::table('jobseeker_profiles')->delete();
        DB::table('recruitment_applications')->delete();
        DB::table('legacyimport_id_map')->where('entity', 'application')->delete();
        $this->app->make(ImportRunner::class)->run('applications', true);

        $snapshot = json_decode((string) DB::table('recruitment_applications')->value('profile_snapshot'), true);
        $this->assertEqualsCanonicalizing(['version', 'name', 'email', 'migrated', 'migrated_at'], array_keys($snapshot));
        $this->assertSame(['Budi Santoso', 'budi@example.test'], [$snapshot['name'], $snapshot['email']]);
    }

    public function test_the_resume_is_copied_from_the_migrated_resume_and_a_missing_one_leaves_none(): void
    {
        $withResume = $this->application('All Applications', ['candidate_resume_id' => $this->resume()]);
        $other = $this->legacyUser(['name' => 'Budi Santoso'], ['phone' => '08111111111']);
        $broken = $this->application('All Applications', ['candidate_id' => $this->legacyCandidateId($other), 'candidate_resume_id' => 9999]);

        $report = $this->import();

        $rows = DB::table('recruitment_applications')->orderBy('id')->get();
        $this->assertMatchesRegularExpression('#^recruitment/applications/[0-9a-f]{40}\.pdf$#', $rows[0]->resume_path);
        $this->assertSame(['cv.pdf', strlen(self::PDF)], [$rows[0]->resume_name, (int) $rows[0]->resume_size_bytes]);
        $this->assertSame(self::PDF, Storage::get($rows[0]->resume_path));
        $this->assertNull($rows[1]->resume_path);
        $this->assertSame(1, $this->entity($report)['notes']['resume-not-migrated']);
        $this->assertSame([$withResume, $broken], DB::table('legacyimport_id_map')->where('entity', 'application')->orderBy('new_id')->pluck('legacy_id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_a_long_cover_letter_is_kept_whole_below_the_column_limit_and_cut_above_it(): void
    {
        $this->application('All Applications', ['cover_letter' => str_repeat('a', 5000)]);
        $other = $this->legacyUser(['name' => 'Budi Santoso']);
        $long = $this->application('All Applications', ['candidate_id' => $this->legacyCandidateId($other), 'cover_letter' => str_repeat('é', 40000)]);

        $report = $this->import();

        $lengths = DB::table('recruitment_applications')->orderBy('id')->pluck('cover_letter')->map(fn (string $text): int => strlen($text))->all();
        $this->assertSame([5000, 60000], $lengths);
        $this->assertSame([['legacy_id' => $long, 'reason' => 'cover-letter-cut']], $this->entity($report)['flagged_rows']);
    }

    // ----- the pipeline groups -----

    public function test_the_default_group_names_map_to_stages_and_a_moved_application_has_a_system_entry(): void
    {
        $users = [];

        foreach (['No Group', 'All Applications', 'Shortlisted', 'Interview', 'Rejected'] as $index => $group) {
            $users[$group] = $this->legacyUser(['name' => "Applicant {$index}"], ['phone' => '0812345678'.$index]);
            $this->application($group, ['candidate_id' => $this->legacyCandidateId($users[$group])]);
        }

        $this->import();

        $types = DB::table('recruitment_applications')->orderBy('id')->pluck('stage_type')->all();
        $this->assertSame(['applied', 'applied', 'screening', 'interview', 'rejected'], $types);

        foreach (DB::table('recruitment_applications')->orderBy('id')->get() as $application) {
            $this->assertSame($application->stage_type, $this->stageType((int) $application->stage_id));
        }

        $moved = DB::table('recruitment_applications')->where('stage_type', 'interview')->value('id');
        $history = DB::table('recruitment_application_stage_history')->where('application_id', $moved)->orderBy('id')->get();
        $this->assertSame(['applied', 'interview'], $history->pluck('to_type')->all());
        $this->assertSame(['applicant', 'system'], $history->pluck('actor_kind')->all());
        $this->assertNull($history[1]->actor_id);
        $this->assertSame('2026-10-08 09:00:00', $history[1]->created_at);
        $this->assertSame('applied', $history[1]->from_type);
        $this->assertNull($history[1]->note);
    }

    public function test_the_mapping_file_overrides_a_name_and_covers_a_custom_group_with_an_extra_stage(): void
    {
        $custom = $this->group('Technical Test');
        $second = $this->legacyUser(['name' => 'Budi Santoso'], ['phone' => '08111111111']);
        $third = $this->legacyUser(['name' => 'Dewi Lestari'], ['phone' => '08222222222']);
        $this->application('Shortlisted');
        $this->legacyRow('applied_jobs', ['candidate_id' => $this->legacyCandidateId($second), 'job_id' => $this->job, 'application_group_id' => $custom, 'created_at' => '2026-08-10 08:00:00']);
        $this->legacyRow('applied_jobs', ['candidate_id' => $this->legacyCandidateId($third), 'job_id' => $this->job, 'application_group_id' => $custom, 'created_at' => '2026-08-10 08:00:00']);

        $report = $this->import(maps: ['groups' => [$this->groups['Shortlisted'] => ['stage' => 'offer'], $custom => ['stage' => 'interview', 'create_stage' => true]]]);

        $this->assertSame(['offer', 'interview', 'interview'], DB::table('recruitment_applications')->orderBy('id')->pluck('stage_type')->all());
        $extra = DB::table('recruitment_stages')->where('name', 'Technical Test')->get();
        $this->assertCount(1, $extra);
        $this->assertSame(['interview', 0], [$extra[0]->type, (int) $extra[0]->is_default]);
        $this->assertSame([(int) $extra[0]->id], DB::table('recruitment_applications')->where('stage_type', 'interview')->pluck('stage_id')->unique()->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(1, $this->entity($report)['notes']['stage-created']);

        $order = DB::table('recruitment_stages')->orderBy('position')->pluck('type')->all();
        $this->assertSame(['applied', 'screening', 'assessment', 'interview', 'offer', 'hired', 'interview', 'rejected', 'withdrawn'], $order);
        $this->assertSame(range(1, 9), DB::table('recruitment_stages')->orderBy('position')->pluck('position')->map(fn ($position): int => (int) $position)->all());
    }

    public function test_an_extra_stage_with_the_former_name_of_a_default_stage_of_the_same_type_reuses_the_default_stage(): void
    {
        $this->application('Interview');

        $this->import(maps: ['groups' => [$this->groups['Interview'] => ['stage' => 'interview', 'create_stage' => true]]]);

        $this->assertSame(8, DB::table('recruitment_stages')->count());
        $this->assertSame('interview', DB::table('recruitment_applications')->value('stage_type'));
        $this->assertSame('Wawancara', DB::table('recruitment_stages')->where('id', DB::table('recruitment_applications')->value('stage_id'))->value('name'));
    }

    public function test_an_unmapped_custom_group_goes_to_applied_and_is_reported_by_group_name(): void
    {
        $custom = $this->group('Culture Fit');
        $this->application('All Applications', ['application_group_id' => $custom]);

        $report = $this->import();

        $this->assertSame('applied', DB::table('recruitment_applications')->value('stage_type'));
        $this->assertSame(1, $this->entity($report)['notes']['group-unmapped']);
        $this->assertSame([['group_id' => $custom, 'group_name' => 'Culture Fit']], $this->entity($report)['unmatched']);
    }

    public function test_an_application_without_a_group_goes_to_applied(): void
    {
        $this->application('none');

        $report = $this->import();

        $this->assertSame('applied', DB::table('recruitment_applications')->value('stage_type'));
        $this->assertSame(1, $this->entity($report)['notes']['group-missing']);
    }

    public function test_an_extra_stage_is_created_beyond_the_default_count_but_a_taken_name_falls_back_to_the_default_stage(): void
    {
        $taken = $this->group('Screening');
        $limit = $this->group('Extra');
        $this->application('All Applications', ['application_group_id' => $taken]);
        $other = $this->legacyUser(['name' => 'Budi Santoso'], ['phone' => '08111111111']);
        $this->legacyRow('applied_jobs', ['candidate_id' => $this->legacyCandidateId($other), 'job_id' => $this->job, 'application_group_id' => $limit, 'created_at' => '2026-08-10 08:00:00']);
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'companies'] as $step) {
            $runner->run($step, true);
        }

        // The company already has its eight default stages (no limit applies to migrated data), and "Screening" is the former English name of the default stage of another type.
        $report = $this->import(maps: ['groups' => [$taken => ['stage' => 'offer', 'create_stage' => true], $limit => ['stage' => 'offer', 'create_stage' => true]]]);

        $this->assertSame(['offer', 'offer'], DB::table('recruitment_applications')->orderBy('id')->pluck('stage_type')->all());
        $this->assertSame(9, DB::table('recruitment_stages')->count());
        $this->assertSame(1, $this->entity($report)['notes']['stage-name-taken']);
        $this->assertArrayNotHasKey('stage-limit-reached', $this->entity($report)['notes']);
    }

    // ----- duplicates -----

    public function test_the_newest_duplicate_keeps_its_stage_and_the_older_ones_are_withdrawn(): void
    {
        $oldest = $this->application('All Applications', ['created_at' => '2026-07-01 08:00:00']);
        $newest = $this->application('Interview', ['created_at' => '2026-08-01 08:00:00']);
        $middle = $this->application('Shortlisted', ['created_at' => '2026-07-15 08:00:00']);

        $report = $this->import();

        $map = DB::table('legacyimport_id_map')->where('entity', 'application')->pluck('new_id', 'legacy_id');
        $byLegacy = fn (int $legacy) => DB::table('recruitment_applications')->where('id', $map[$legacy])->first();
        $this->assertSame(['withdrawn', null], [$byLegacy($oldest)->stage_type, $byLegacy($oldest)->active_key]);
        $this->assertSame(['withdrawn', null], [$byLegacy($middle)->stage_type, $byLegacy($middle)->active_key]);
        $this->assertSame('interview', $byLegacy($newest)->stage_type);
        $this->assertNotNull($byLegacy($newest)->active_key);
        $this->assertNull($byLegacy($oldest)->withdrawn_note);
        $this->assertSame(['applied', 'withdrawn'], DB::table('recruitment_application_stage_history')->where('application_id', $map[$oldest])->orderBy('id')->pluck('to_type')->all());
        $this->assertSame(2, $this->entity($report)['notes']['older-duplicate-withdrawn']);
        $this->assertSame(3, $this->entity($report)['imported']);
    }

    public function test_a_live_application_of_the_new_system_keeps_the_key_and_the_imported_one_becomes_history(): void
    {
        $this->application('Interview');
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'companies', 'profiles', 'resumes', 'jobs'] as $step) {
            $runner->run($step, true);
        }

        $userId = (int) DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $this->user)->value('new_id');
        $jobId = (int) DB::table('job_jobs')->value('id');
        $companyId = (int) DB::table('company_companies')->value('id');
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $companyId)->where('type', 'applied')->value('id');
        DB::table('recruitment_applications')->insert([
            'user_id' => $userId, 'job_id' => $jobId, 'company_id' => $companyId, 'stage_id' => $stage, 'stage_type' => 'applied', 'active_key' => $userId.'-'.$jobId,
            'applied_at' => '2026-09-01 08:00:00', 'created_at' => '2026-09-01 08:00:00', 'updated_at' => '2026-09-01 08:00:00',
        ]);

        $report = $runner->run('applications', true)['report'];

        $this->assertSame(['applied', 'withdrawn'], DB::table('recruitment_applications')->orderBy('id')->pluck('stage_type')->all());
        $this->assertSame([['legacy_id' => (int) DB::connection('legacy')->table('applied_jobs')->value('id'), 'reason' => 'live-application-exists']], $this->entity($report)['flagged_rows']);
    }

    // ----- rows that cannot be imported -----

    public function test_rows_that_cannot_be_imported_are_skipped_with_a_reason(): void
    {
        $this->application('All Applications', ['job_id' => null]);
        $this->application('All Applications', ['candidate_id' => null]);
        $this->application('All Applications', ['candidate_id' => 9999]);
        $demo = $this->legacyUser(['name' => 'Demo', 'is_demo_field' => 1]);
        $this->application('All Applications', ['candidate_id' => $this->legacyCandidateId($demo)]);
        $skippedJob = $this->legacyRow('jobs', ['company_id' => null, 'title' => 'Free text company', 'status' => 'pending', 'apply_on' => 'app', 'company_name' => 'Somewhere']);
        $this->application('All Applications', ['job_id' => $skippedJob]);
        $this->application('Interview');

        $report = $this->import();

        $this->assertSame(['job-missing', 'candidate-missing', 'candidate-missing', 'user-not-imported', 'job-not-imported'], $this->skipReasons($report));
        $this->assertSame(1, DB::table('recruitment_applications')->count());
        $this->assertSame([6, 1, 5], [$this->entity($report)['read'], $this->entity($report)['imported'], $this->entity($report)['skipped']]);
    }

    public function test_legacy_answers_are_not_migrated_and_counted(): void
    {
        $this->application('All Applications', ['answers' => '[{"question":"Why?","answer":"Because"}]']);

        $report = $this->import();

        $this->assertSame(1, $this->entity($report)['notes']['answers-not-migrated']);
        $this->assertSame(0, DB::table('recruitment_application_answers')->count());
    }

    // ----- runs -----

    public function test_a_second_run_adds_nothing_and_a_dry_run_writes_nothing(): void
    {
        $this->application('Interview', ['candidate_resume_id' => $this->resume()]);
        $dry = $this->import(false);

        $this->assertSame([1, 0, 0], [$this->entity($dry)['imported'], DB::table('recruitment_applications')->count(), DB::table('legacyimport_id_map')->where('entity', 'application')->count()]);
        $this->assertSame([], Storage::allFiles('recruitment'));

        $this->import();
        $second = $this->import();

        $this->assertSame(1, DB::table('recruitment_applications')->count());
        $this->assertSame(2, DB::table('recruitment_application_stage_history')->count());
        $this->assertSame([0, 1], [$this->entity($second)['imported'], $this->entity($second)['notes']['already-imported']]);
        $this->assertCount(1, Storage::allFiles('recruitment/applications'));
    }

    public function test_a_failure_in_a_batch_leaves_no_resume_copy_behind(): void
    {
        $this->application('All Applications', ['candidate_resume_id' => $this->resume()]);
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'companies', 'profiles', 'resumes', 'jobs'] as $step) {
            $runner->run($step, true);
        }

        $real = $this->app->make(ApplicationWriter::class);
        $this->app->bind(ApplicationWriter::class, fn (): ApplicationWriter => new class($real) implements ApplicationWriter
        {
            public function __construct(private readonly ApplicationWriter $real) {}

            public function defaultStages(int $companyId): array
            {
                return $this->real->defaultStages($companyId);
            }

            public function stageByName(int $companyId, string $name): ?array
            {
                return $this->real->stageByName($companyId, $name);
            }

            public function insertStage(int $companyId, string $type, string $name, string $createdAt): int
            {
                return $this->real->insertStage($companyId, $type, $name, $createdAt);
            }

            public function hasLiveApplication(int $userId, int $jobId): bool
            {
                return $this->real->hasLiveApplication($userId, $jobId);
            }

            public function hasProfile(int $userId): bool
            {
                return $this->real->hasProfile($userId);
            }

            public function insertApplication(array $columns): int
            {
                return $this->real->insertApplication($columns);
            }

            public function insertHistory(int $applicationId, ?int $fromStageId, int $toStageId, ?string $fromType, string $toType, string $actorKind, ?int $actorId, string $createdAt): void
            {
                throw new \RuntimeException('The history cannot be written.');
            }
        });

        try {
            $this->app->make(ImportRunner::class)->run('applications', true);
            $this->fail('The run should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('The history cannot be written.', $e->getMessage());
        }

        $this->assertSame(0, DB::table('recruitment_applications')->count());
        $this->assertSame([], Storage::allFiles('recruitment/applications'));
    }

    // ----- the mapping file -----

    /**
     * @return array<string, array{array<int|string, mixed>, string}>
     */
    public static function invalidMappings(): array
    {
        return [
            'unknown stage type' => [[142 => ['stage' => 'dinner']], 'Group mapping entry "142"'],
            'missing stage' => [[142 => []], 'Group mapping entry "142"'],
            'create stage on a default-only type' => [[142 => ['stage' => 'applied', 'create_stage' => true]], 'cannot create a stage of the type "applied"'],
            'create stage that is not a boolean' => [[142 => ['stage' => 'interview', 'create_stage' => 'yes']], 'not true or false'],
            'unknown field' => [[142 => ['stage' => 'interview', 'name' => 'x']], 'unknown field "name"'],
            'not an object' => [[142 => 'interview'], 'must be an object'],
            'key that is not a group id' => [['abc' => ['stage' => 'interview']], 'Group mapping entry "abc"'],
            'a list' => [[['stage' => 'interview']], 'Group mapping entry "0"'],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $mapping
     */
    #[DataProvider('invalidMappings')]
    public function test_an_invalid_mapping_is_rejected_before_anything_is_imported(array $mapping, string $message): void
    {
        $this->application();
        $runner = $this->app->make(ImportRunner::class);
        $users = UserModel::query()->count();

        try {
            $runner->run(null, true, ['groups' => $mapping]);
            $this->fail('The mapping should have been rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->assertSame($users, UserModel::query()->count());
        $this->assertSame(0, DB::table('legacyimport_id_map')->count());
    }

    public function test_duplicate_keys_of_the_mapping_text_are_found(): void
    {
        $json = '{"142": {"stage": "interview"}, "7": {"stage": "offer", "create_stage": true}, "142": {"stage": "offer"}}';

        $this->assertSame(['142'], StageGroupMapping::duplicateKeys($json));
        $this->assertSame([], StageGroupMapping::duplicateKeys('{"1": {"stage": "offer"}, "2": {"stage": "offer"}}'));
    }

    // ----- through the real API -----

    public function test_every_imported_application_is_marked_with_the_time_of_the_import(): void
    {
        $this->application('Interview', ['created_at' => '2026-07-01 08:00:00']);
        $this->application('Interview', ['created_at' => '2026-08-01 08:00:00']);
        $this->import();

        $this->assertSame(['2026-10-08 09:00:00', '2026-10-08 09:00:00'], DB::table('recruitment_applications')->orderBy('id')->pluck('migrated_at')->all());
        $this->assertSame(0, DB::table('recruitment_applications')->whereColumn('stage_entered_at', '>', 'migrated_at')->count());
    }

    public function test_the_import_summaries_tell_each_side_what_came_along(): void
    {
        $this->application('All Applications', ['created_at' => '2026-07-01 08:00:00']);
        $this->application('Interview', ['created_at' => '2026-08-01 08:00:00']);
        $this->application('Shortlisted', ['created_at' => '2026-07-15 08:00:00', 'candidate_resume_id' => $this->resume()]);
        $this->import();

        $newId = fn (string $entity, int $legacy): int => (int) DB::table('legacyimport_id_map')->where('entity', $entity)->where('legacy_id', $legacy)->value('new_id');
        $item = fn (array $items, string $code): ?array => collect($items)->firstWhere('code', $code);

        $seeker = json_decode((string) DB::table('jobseeker_import_summaries')->where('user_id', $newId('user', $this->user))->value('items'), true);
        $this->assertEquals(['count' => 3, 'detail' => ['withdrawn_duplicates' => 2, 'no_resume' => 2]], array_diff_key($item($seeker, 'applications_imported'), ['code' => 1]));
        $this->assertNotNull($item($seeker, 'password_reset_needed'));

        $company = json_decode((string) DB::table('company_import_summaries')->where('company_id', $newId('company', $this->company))->value('items'), true);
        $this->assertSame(['count' => 3, 'detail' => ['withdrawn_duplicates' => 2]], array_diff_key($item($company, 'applications_imported'), ['code' => 1]));
        $this->assertSame(1, $item($company, 'jobs_imported')['count']);

        $this->assertSame(1, DB::table('jobseeker_import_summaries')->where('user_id', $newId('user', $this->user))->count());
    }

    public function test_the_summary_of_a_second_run_does_not_count_twice(): void
    {
        $this->application();
        $this->import();
        $this->import();

        $count = fn (): int => (int) collect(json_decode((string) DB::table('company_import_summaries')->value('items'), true))->firstWhere('code', 'applications_imported')['count'];

        $this->assertSame(1, $count());
        $this->assertSame(1, DB::table('company_import_summaries')->count());
    }

    public function test_an_imported_application_is_served_and_moved_through_the_company_api(): void
    {
        $this->application('Shortlisted', ['candidate_resume_id' => $this->resume()]);
        $this->import();

        $applicationId = (int) DB::table('recruitment_applications')->value('id');
        $ownerId = (int) DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $this->owner)->value('new_id');
        $token = UserModel::query()->findOrFail($ownerId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;
        $this->app['auth']->forgetGuards();

        $show = $this->withToken($token)->getJson('/api/v1/company/applications/'.$applicationId);
        $show->assertOk();
        $this->assertSame('Siti Rahma', $show->json('data.applicant.name'));
        $this->assertTrue($show->json('data.applicant.migrated'));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->get('/api/v1/company/applications/'.$applicationId.'/resume')->assertOk();

        $list = $this->withToken($token)->getJson('/api/v1/company/jobs/'.DB::table('job_jobs')->value('id').'/applications');
        $list->assertOk();
        $this->assertSame(1, $list->json('meta.total') ?? count($list->json('data')));
    }
}
