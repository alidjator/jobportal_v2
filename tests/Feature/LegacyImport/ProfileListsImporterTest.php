<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class ProfileListsImporterTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    private int $candidate;

    private int $bachelor;

    private int $php;

    private int $english;

    private int $fullTime;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->setUpLegacyDatabase();

        $this->bachelor = $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]);
        $this->php = $this->skillId('php');
        $this->english = $this->referenceId('reference_languages', 'english', ['code' => 'en']);
        $this->fullTime = $this->referenceId('reference_job_types', 'full-time');
        $this->legacyMasterItem('education', 1, ['en' => 'Bachelor']);
        $this->legacyMasterItem('education', 2, ['en' => 'Doctor of Everything']);
        $this->legacyMasterItem('skills', 1, ['en' => 'PHP']);
        $this->legacyMasterItem('skills', 2, ['en' => 'Underwater Welding']);
        $this->legacyLanguage(1, 'English');
        $this->legacyLanguage(2, 'Klingon');
        DB::table('reference_languages')->where('id', $this->english)->update(['name' => json_encode(['id' => 'Inggris', 'en' => 'English'])]);

        $this->candidate = $this->legacyCandidateId($this->legacyUser(['email' => 'siti@example.test']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function import(string $entity, bool $write = true): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'profiles'] as $parent) {
            $runner->run($parent, true);
        }

        return $runner->run($entity, $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report, string $name): array
    {
        return $report->toArray(true)['entities'][$name];
    }

    /** @param  array<string, mixed>  $attributes */
    private function experience(array $attributes = []): int
    {
        return $this->legacyRow('candidate_experiences', [
            'candidate_id' => $this->candidate, 'company' => 'PT Maju', 'job_title' => 'Backend Developer', 'start' => '2020-03', 'end' => '2022-06', 'currently_working' => 0,
            'created_at' => '2024-03-01 08:00:00', 'updated_at' => '2024-03-02 08:00:00', ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function education(array $attributes = []): int
    {
        return $this->legacyRow('candidate_education', [
            'candidate_id' => $this->candidate, 'level' => 1, 'name' => 'ITB', 'year' => '2019', 'created_at' => '2024-03-01 08:00:00', 'updated_at' => '2024-03-02 08:00:00', ...$attributes,
        ]);
    }

    // ----- experiences -----

    public function test_an_experience_is_imported_with_its_dates_read_from_the_free_text(): void
    {
        $this->experience(['responsibilities' => '<p>Built <b>APIs</b>.</p>', 'employment_type' => 'Full Time', 'department' => 'Tech']);
        $this->experience(['company' => 'PT Baru', 'start' => 'Maret 2023', 'end' => null, 'currently_working' => 1, 'job_title' => null, 'designation' => 'Lead']);
        $this->experience(['company' => 'PT Tiga', 'start' => '15/01/2018', 'end' => '12/2019']);

        $report = $this->import('experiences');

        $rows = DB::table('jobseeker_experiences')->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame(['Backend Developer', 'PT Maju', '2020-03-01', '2022-06-01', 0, 'Built APIs.', $this->fullTime], [$rows[0]->job_title, $rows[0]->company_name, $rows[0]->start_date, $rows[0]->end_date, (int) $rows[0]->is_current, $rows[0]->description, (int) $rows[0]->job_type_id]);
        $this->assertSame(['Lead', '2023-03-01', null, 1, null], [$rows[1]->job_title, $rows[1]->start_date, $rows[1]->end_date, (int) $rows[1]->is_current, $rows[1]->job_type_id]);
        $this->assertSame(['2018-01-01', '2019-12-01'], [$rows[2]->start_date, $rows[2]->end_date]);
        $this->assertSame('2024-03-01 08:00:00', $rows[0]->created_at);
        $this->assertSame(3, $this->entity($report, 'experiences')['imported']);
    }

    public function test_an_experience_that_breaks_the_new_rules_is_skipped_with_its_reason(): void
    {
        $noCompany = $this->experience(['company' => ' ']);
        $shortTitle = $this->experience(['job_title' => 'QA', 'designation' => null]);
        $badStart = $this->experience(['start' => 'sometime']);
        $noEnd = $this->experience(['end' => null]);
        $endBeforeStart = $this->experience(['start' => '2022-06', 'end' => '2020-03']);
        $future = $this->experience(['start' => '2030-01', 'end' => '2031-01']);

        $report = $this->entity($this->import('experiences'), 'experiences');

        $this->assertSame([
            $noCompany => 'company-invalid', $shortTitle => 'job-title-invalid', $badStart => 'start-date-unreadable', $noEnd => 'end-date-unreadable',
            $endBeforeStart => 'invalid-end_date', $future => 'invalid-start_date',
        ], collect($report['skipped_rows'])->pluck('reason', 'legacy_id')->all());
        $this->assertSame(0, DB::table('jobseeker_experiences')->count());
    }

    public function test_at_most_thirty_experiences_per_user_are_kept(): void
    {
        for ($i = 0; $i < 32; $i++) {
            $this->experience(['company' => 'PT '.$i]);
        }

        $report = $this->entity($this->import('experiences'), 'experiences');

        $this->assertSame(30, DB::table('jobseeker_experiences')->count());
        $this->assertSame(['over-limit'], collect($report['skipped_rows'])->pluck('reason')->unique()->values()->all());
        $this->assertSame(2, $report['skipped']);
    }

    // ----- educations -----

    public function test_an_education_is_imported_with_the_year_as_start_and_end(): void
    {
        $this->education(['field' => 'Informatics', 'grade' => '3,75', 'notes' => '<p>Cum laude</p>']);

        $this->import('educations');

        $row = DB::table('jobseeker_educations')->first();
        $this->assertSame([$this->bachelor, 'ITB', 'Informatics', 2019, 2019, 0, '3.75', 'Cum laude'], [(int) $row->education_level_id, $row->institution, $row->field_of_study, (int) $row->start_year, (int) $row->end_year, (int) $row->is_current, $row->gpa, $row->description]);
    }

    public function test_an_invalid_gpa_is_emptied_but_the_entry_is_kept(): void
    {
        $this->education(['grade' => '85']);
        $this->education(['grade' => 'A', 'name' => 'UI']);

        $report = $this->entity($this->import('educations'), 'educations');

        $this->assertSame([null, null], DB::table('jobseeker_educations')->orderBy('id')->pluck('gpa')->all());
        $this->assertSame(2, $report['notes']['gpa-invalid']);
        $this->assertSame(2, $report['imported']);
    }

    public function test_an_education_that_cannot_be_imported_is_skipped_with_its_reason(): void
    {
        $noLevel = $this->education(['level' => null]);
        $unknownLevel = $this->education(['level' => 2]);
        $noName = $this->education(['name' => '']);
        $noYear = $this->education(['year' => 'last year']);
        $oldYear = $this->education(['year' => '1900']);
        $future = $this->education(['year' => '2099']);

        $report = $this->entity($this->import('educations'), 'educations');

        $this->assertSame([
            $noLevel => 'education-level-unmatched', $unknownLevel => 'education-level-unmatched', $noName => 'institution-missing', $noYear => 'year-unreadable',
            $oldYear => 'invalid-start_year', $future => 'invalid-start_year',
        ], collect($report['skipped_rows'])->pluck('reason', 'legacy_id')->all());
    }

    // ----- skills and languages -----

    public function test_skills_are_imported_in_order_without_duplicates_and_a_legacy_only_skill_is_created_as_a_master(): void
    {
        $this->legacyRow('candidate_skill', ['candidate_id' => $this->candidate, 'skill_id' => 1]);
        $duplicate = $this->legacyRow('candidate_skill', ['candidate_id' => $this->candidate, 'skill_id' => 1]);
        $legacyOnly = $this->legacyRow('candidate_skill', ['candidate_id' => $this->candidate, 'skill_id' => 2]);
        $nothing = $this->legacyRow('candidate_skill', ['candidate_id' => $this->candidate, 'skill_id' => null]);

        $report = $this->entity($this->import('skills'), 'skills');

        $created = (int) DB::table('reference_skills')->where('slug', 'underwater-welding')->value('id');
        $this->assertSame([[$this->php, 1], [$created, 2]], DB::table('jobseeker_skills')->orderBy('position')->get(['skill_id', 'position'])->map(fn ($r): array => [(int) $r->skill_id, (int) $r->position])->all());
        $this->assertSame([$duplicate => 'duplicate', $nothing => 'skill-unmatched'], collect($report['skipped_rows'])->pluck('reason', 'legacy_id')->all());
    }

    public function test_languages_are_imported_as_conversational_and_unmatched_names_are_skipped(): void
    {
        $this->legacyRow('candidate_language', ['candidate_id' => $this->candidate, 'candidate_language_id' => 1]);
        $unmatched = $this->legacyRow('candidate_language', ['candidate_id' => $this->candidate, 'candidate_language_id' => 2]);

        $report = $this->entity($this->import('languages'), 'languages');

        $this->assertSame([[$this->english, 'conversational']], DB::table('jobseeker_languages')->get(['language_id', 'proficiency'])->map(fn ($r): array => [(int) $r->language_id, $r->proficiency])->all());
        $this->assertSame([['legacy_id' => $unmatched, 'reason' => 'language-unmatched']], $report['skipped_rows']);
    }

    // ----- what the lists share -----

    public function test_a_row_of_a_candidate_without_an_imported_profile_is_skipped(): void
    {
        $demo = $this->legacyCandidateId($this->legacyUser(['email' => 'demo@example.test', 'is_demo_field' => 1]));
        $row = $this->legacyRow('candidate_experiences', ['candidate_id' => $demo, 'company' => 'PT Maju', 'job_title' => 'Backend Developer', 'start' => '2020-03', 'end' => '2022-06']);

        $report = $this->entity($this->import('experiences'), 'experiences');

        $this->assertSame([['legacy_id' => $row, 'reason' => 'profile-not-imported']], $report['skipped_rows']);
        $this->assertSame(0, DB::table('jobseeker_experiences')->count());
    }

    public function test_running_it_again_adds_nothing_and_a_dry_run_writes_nothing(): void
    {
        $this->experience();
        $this->import('experiences');
        DB::table('jobseeker_experiences')->update(['company_name' => 'Edited in the new system']);
        $this->experience(['company' => 'PT Baru']);

        $report = $this->entity($this->import('experiences'), 'experiences');

        $this->assertSame(['Edited in the new system', 'PT Baru'], DB::table('jobseeker_experiences')->orderBy('id')->pluck('company_name')->all());
        $this->assertSame(1, $report['notes']['already-imported']);

        $this->experience(['company' => 'PT Lima']);
        $dry = $this->entity($this->import('experiences', false), 'experiences');
        $this->assertSame(1, $dry['imported']);
        $this->assertSame(2, DB::table('jobseeker_experiences')->count());
    }

    // ----- the result seen through the API -----

    public function test_the_job_seeker_sees_the_imported_lists_through_the_api(): void
    {
        $this->experience();
        $this->education();
        $this->legacyRow('candidate_skill', ['candidate_id' => $this->candidate, 'skill_id' => 1]);
        $this->legacyRow('candidate_language', ['candidate_id' => $this->candidate, 'candidate_language_id' => 1]);
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'profiles', 'experiences', 'educations', 'skills', 'languages'] as $entity) {
            $runner->run($entity, true);
        }

        $user = UserModel::query()->firstOrFail();
        $token = $user->createToken('device', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me/profile')->assertOk()
            ->assertJsonCount(1, 'data.experiences')->assertJsonCount(1, 'data.educations')->assertJsonCount(1, 'data.skills')->assertJsonCount(1, 'data.languages')
            ->assertJsonPath('data.experiences.0.job_title', 'Backend Developer')->assertJsonPath('data.educations.0.institution', 'ITB')
            ->assertJsonPath('data.languages.0.proficiency', 'conversational');
    }
}
