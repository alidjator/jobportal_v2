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

final class JobsImporterTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    /** @var array<string, int> */
    private array $new = [];

    private int $owner;

    private int $company;

    /** @var array{province_id: int, city_id: int, subdistrict_id: int, village_id: int, postal_code_id: int} */
    private array $chain;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->setUpLegacyDatabase();
        $this->chain = $this->locationChain();
        $this->industryId();
        $this->organizationTypeId();

        $this->new = [
            'category' => $this->referenceId('reference_job_categories', 'engineering'),
            'role' => $this->referenceId('reference_job_roles', 'backend-developer'),
            'type' => $this->referenceId('reference_job_types', 'full-time'),
            'level' => $this->referenceId('reference_job_levels', 'senior'),
            'time' => $this->referenceId('reference_work_times', 'day-shift'),
            'experience' => $this->referenceId('reference_experience_levels', 'five-years'),
            'education' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]),
            'period' => $this->referenceId('reference_salary_periods', 'monthly'),
            'php' => $this->skillId('php'),
            'laravel' => $this->skillId('laravel'),
            'teamwork' => $this->referenceId('reference_skills', 'teamwork', ['kind' => 'soft']),
            'insurance' => $this->benefitId('insurance'),
        ];

        foreach ([
            ['job_categories', 'engineering'], ['job_roles', 'backend-developer'], ['job_types', 'full-time'], ['job_levels', 'senior'], ['job_work_times', 'day-shift'],
            ['experiences', 'five-years'], ['education', 'bachelor'], ['salary_types', 'monthly'], ['skills', 'php'], ['skills', 'laravel'], ['benefits', 'insurance'],
            ['industry_types', 'technology'], ['organization_types', 'private-company'],
        ] as $index => [$master, $name]) {
            $existing = DB::connection('legacy')->table($master)->count();
            $this->legacyMasterItem($master, $existing + 1, ['en' => $name]);
        }

        $this->owner = $this->legacyUser(['name' => 'PT Maju Jaya', 'role' => 'company']);
        $this->company = $this->legacyCompany($this->owner, 1, 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function import(bool $write = true): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);
        $runner->run('masters', true);
        $runner->run('accounts', true);
        $runner->run('companies', true);

        return $runner->run('jobs', $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report): array
    {
        return $report->toArray(true)['entities']['jobs'];
    }

    /** @return list<string> the skip reasons of the report */
    private function skipReasons(ImportReport $report): array
    {
        return array_map(static fn (array $row): string => $row['reason'], $this->entity($report)['skipped_rows']);
    }

    /** @return list<string> the flagged reasons of the report */
    private function flags(ImportReport $report): array
    {
        return array_map(static fn (array $row): string => $row['reason'], $this->entity($report)['flagged_rows'] ?? []);
    }

    /**
     * A legacy job that is complete under the new rules; any column can be overridden.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function legacyJob(array $attributes = []): int
    {
        return $this->legacyRow('jobs', [
            'company_id' => $this->company, 'category_id' => 1, 'role_id' => 1, 'job_type_id' => 1, 'job_level_id' => 1, 'work_time' => 1, 'experience_id' => 1, 'education_id' => 1,
            'title' => 'Backend Developer', 'vacancies' => '3', 'deadline' => '2026-12-31', 'status' => 'active', 'apply_on' => 'app', 'is_remote' => 0,
            'description' => '<p>We are looking for a backend developer who loves clean code and shipping.</p>',
            'region' => 32, 'district' => 3273, 'subdistrict' => 327301, 'hard_skill' => '<ul><li>PHP</li></ul>',
            'created_at' => '2026-08-01 08:00:00', 'updated_at' => '2026-08-02 09:00:00', ...$attributes,
        ]);
    }

    private function job(): object
    {
        return DB::table('job_jobs')->orderBy('id')->first();
    }

    // ----- what is imported and how -----

    public function test_a_complete_job_is_imported_with_its_content_skills_benefits_and_assignee(): void
    {
        $job = $this->legacyJob([
            'title' => '  Backend Developer  ', 'address' => 'Jl. Merdeka 1', 'min_salary' => 8000000, 'max_salary' => 12000000, 'salary_type_id' => 1, 'show_salary' => '1',
            'soft_skill' => '<p>Teamwork</p>', 'notes' => '<p>Send your best work.</p>', 'benefits' => 'free text that is not carried',
            'apply_email' => 'hr@example.test',
        ]);
        $this->legacyRow('job_skills', ['job_id' => $job, 'skill_id' => 2]);
        $this->legacyRow('job_benefit', ['job_id' => $job, 'benefit_id' => 1]);

        $report = $this->import();

        $row = $this->job();
        $companyId = (int) DB::table('company_companies')->value('id');
        $ownerId = (int) DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $this->owner)->value('new_id');

        $this->assertSame([$companyId, 'Backend Developer', 'backend-developer', 'active', 'onsite'], [(int) $row->company_id, $row->title, $row->slug, $row->status, $row->work_mode]);
        $this->assertSame([$this->new['category'], $this->new['role'], $this->new['type'], $this->new['level'], $this->new['time'], $this->new['experience'], $this->new['education']], [
            (int) $row->category_id, (int) $row->role_id, (int) $row->job_type_id, (int) $row->job_level_id, (int) $row->work_time_id, (int) $row->experience_level_id, (int) $row->education_level_id,
        ]);
        $this->assertSame([3, '2026-12-31', 8000000, 12000000, $this->new['period'], 1], [(int) $row->vacancies, $row->deadline, (int) $row->salary_min, (int) $row->salary_max, (int) $row->salary_period_id, (int) $row->salary_visible]);
        $this->assertSame([$this->chain['province_id'], $this->chain['city_id'], $this->chain['subdistrict_id'], null, null], [(int) $row->province_id, (int) $row->city_id, (int) $row->subdistrict_id, $row->village_id, $row->postal_code_id]);
        $this->assertSame('Jl. Merdeka 1', $row->address_line);
        $this->assertSame([$ownerId, '2026-08-01 08:00:00', '2026-08-01 08:00:00', '2026-08-02 09:00:00'], [(int) $row->created_by_user_id, $row->published_at, $row->created_at, $row->updated_at]);
        $this->assertNull($row->reviewed_at);

        $this->assertStringContainsString('We are looking for a backend developer', $row->description);
        $this->assertStringContainsString("## Hard skills\n\n- PHP", $row->description);
        $this->assertStringContainsString("## Soft skills\n\nTeamwork", $row->description);
        $this->assertStringContainsString("## Notes\n\nSend your best work.", $row->description);
        $this->assertStringNotContainsString('free text', $row->description);

        $this->assertEqualsCanonicalizing([$this->new['laravel'], $this->new['php'], $this->new['teamwork']], array_map('intval', DB::table('job_job_skills')->where('job_id', $row->id)->pluck('skill_id')->all()));
        $this->assertSame([$this->new['insurance']], array_map('intval', DB::table('job_job_benefits')->where('job_id', $row->id)->pluck('benefit_id')->all()));
        $this->assertSame([$ownerId], array_map('intval', DB::table('job_assignments')->where('job_id', $row->id)->pluck('user_id')->all()));
        $this->assertSame(0, DB::table('job_job_tags')->count());
        $this->assertSame((int) $row->id, (int) DB::table('legacyimport_id_map')->where('entity', 'job')->where('legacy_id', $job)->value('new_id'));
        $this->assertSame(1, $this->entity($report)['imported']);
        $this->assertSame([], $this->flags($report));
    }

    public function test_an_imported_live_job_is_served_by_the_public_api(): void
    {
        $this->legacyJob(['deadline' => '2026-12-31']);

        $this->import();

        $this->getJson('/api/v1/jobs/'.$this->job()->id)->assertOk()->assertJsonPath('data.title', 'Backend Developer')->assertJsonPath('data.vacancies', 3);
    }

    public function test_the_status_follows_the_legacy_status_and_the_deadline(): void
    {
        $this->legacyJob(['title' => 'Pending job', 'status' => 'pending']);
        $this->legacyJob(['title' => 'Active job', 'status' => 'active']);
        $this->legacyJob(['title' => 'Expired job', 'status' => 'expired', 'deadline' => '2026-09-01']);
        $this->legacyJob(['title' => 'Overdue job', 'status' => 'active', 'deadline' => '2026-10-06']);
        $this->legacyJob(['title' => 'Last day job', 'status' => 'active', 'deadline' => '2026-10-07']);

        $report = $this->import();

        $rows = DB::table('job_jobs')->orderBy('id')->get(['status', 'published_at'])->all();
        $this->assertSame(['pending_review', 'active', 'expired', 'expired', 'active'], array_map(static fn (object $row): string => $row->status, $rows));
        $this->assertNull($rows[0]->published_at);
        $this->assertSame('2026-08-01 08:00:00', $rows[3]->published_at);
        $this->assertSame(['deadline-passed-expired'], $this->flags($report));
    }

    public function test_a_job_that_applies_by_email_or_url_is_closed_even_when_incomplete(): void
    {
        $this->legacyJob(['title' => 'By email', 'apply_on' => 'email', 'apply_email' => 'hr@example.test']);
        $this->legacyJob(['title' => 'By link', 'apply_on' => 'custom_url', 'apply_url' => 'https://example.test/apply', 'description' => '<p>short</p>', 'status' => 'pending']);

        $report = $this->import();

        $this->assertSame(['closed', 'closed'], DB::table('job_jobs')->orderBy('id')->pluck('status')->all());
        $this->assertSame(['closed-incomplete-description'], $this->flags($report));
        $this->assertNull(DB::table('job_jobs')->orderByDesc('id')->value('published_at'));
    }

    public function test_a_job_that_fails_the_publish_rules_becomes_a_draft_with_the_first_failing_rule_flagged(): void
    {
        $this->legacyJob(['title' => 'No skills', 'hard_skill' => null]);
        $this->legacyJob(['title' => 'No vacancies', 'vacancies' => 'many']);
        $this->legacyJob(['title' => 'No level', 'job_level_id' => null]);
        $this->legacyJob(['title' => 'Short text', 'description' => '<p>short</p>']);
        $this->legacyJob(['title' => 'No deadline', 'deadline' => null]);
        $this->legacyJob(['title' => 'No location', 'region' => null]);
        $this->legacyJob(['title' => 'Remote', 'region' => null, 'is_remote' => 1]);
        $this->legacyJob(['title' => 'Late review', 'status' => 'pending', 'deadline' => '2026-10-01']);

        $report = $this->import();

        $statuses = DB::table('job_jobs')->orderBy('id')->pluck('status', 'title')->all();
        $this->assertSame('active', $statuses['Remote']);
        $this->assertSame(['draft'], array_values(array_unique(array_diff_key($statuses, ['Remote' => 1]))));
        $this->assertSame([
            'draft-incomplete-skills', 'draft-incomplete-vacancies', 'draft-incomplete-job-level', 'draft-incomplete-description',
            'draft-incomplete-deadline', 'draft-incomplete-location', 'draft-incomplete-deadline-passed',
        ], $this->flags($report));
        $this->assertSame(1, $this->entity($report)['notes']['vacancies-unreadable']);
        $this->assertNull(DB::table('job_jobs')->where('title', 'No skills')->value('published_at'));
    }

    public function test_a_draft_keeps_the_columns_it_has(): void
    {
        $this->legacyJob(['hard_skill' => null, 'min_salary' => 5000000, 'max_salary' => 6000000, 'salary_type_id' => 1]);

        $this->import();

        $row = $this->job();
        $this->assertSame('draft', $row->status);
        $this->assertSame([$this->new['category'], 5000000], [(int) $row->category_id, (int) $row->salary_min]);
    }

    // ----- rows that are not imported -----

    public function test_rows_that_cannot_be_imported_are_skipped_with_a_reason(): void
    {
        $other = $this->legacyUser(['name' => 'PT Tanpa Industri', 'role' => 'company']);
        $unmatched = $this->legacyCompany($other, 99, 1);
        $this->legacyJob(['title' => 'A pending edit copy', 'parent_job_id' => 5]);
        $this->legacyJob(['title' => 'Admin job', 'company_id' => null, 'company_name' => 'External Inc']);
        $this->legacyJob(['title' => 'Company not imported', 'company_id' => $unmatched]);
        $this->legacyJob(['title' => '   ']);
        $this->legacyJob(['title' => 'Ab']);
        $this->legacyJob(['title' => 'Fine']);

        $report = $this->import();

        $this->assertSame(['pending-edit-skipped', 'company-missing', 'company-not-imported', 'title-missing', 'title-too-short'], $this->skipReasons($report));
        $this->assertSame(['Fine'], DB::table('job_jobs')->pluck('title')->all());
        $this->assertSame([6, 1, 5], [$this->entity($report)['read'], $this->entity($report)['imported'], $this->entity($report)['skipped']]);
    }

    public function test_a_long_title_is_cut_and_the_slug_is_made_from_it(): void
    {
        $this->legacyJob(['title' => str_repeat('Senior Engineer ', 12)]);

        $report = $this->import();

        $row = $this->job();
        $this->assertSame(150, mb_strlen($row->title));
        $this->assertLessThanOrEqual(100, strlen($row->slug));
        $this->assertStringStartsWith('senior-engineer-senior-engineer', $row->slug);
        $this->assertSame(1, $this->entity($report)['notes']['title-cut']);
    }

    public function test_a_title_with_no_letters_gets_the_plain_slug(): void
    {
        $this->legacyJob(['title' => '!!! ???']);

        $this->import();

        $this->assertSame('job', $this->job()->slug);
    }

    // ----- masters, skills and benefits -----

    public function test_a_master_with_no_counterpart_is_noted_and_makes_the_job_a_draft_when_required(): void
    {
        $this->legacyMasterItem('job_levels', 50, ['en' => 'Galactic Overlord']);
        $this->legacyJob(['job_level_id' => 50]);

        $report = $this->import();

        $this->assertNull($this->job()->job_level_id);
        $this->assertSame('draft', $this->job()->status);
        $this->assertSame(1, $this->entity($report)['notes']['job_levels-unmatched']);
    }

    public function test_the_skills_of_the_links_and_of_the_text_are_joined_without_duplicates(): void
    {
        $job = $this->legacyJob([
            'hard_skill' => '<ul><li>PHP</li><li>php</li><li>Laravel</li><li>Quantum Cooking</li></ul>',
            'soft_skill' => 'Teamwork; A very long sentence that explains how the candidate should behave in a meeting with colleagues',
        ]);
        $this->legacyRow('job_skills', ['job_id' => $job, 'skill_id' => 1]);
        $this->legacyRow('job_skills', ['job_id' => $job, 'skill_id' => 99]);
        $this->legacyRow('job_skills', ['job_id' => $job, 'skill_id' => null]);

        $report = $this->import();

        $skills = array_map('intval', DB::table('job_job_skills')->where('job_id', $this->job()->id)->orderBy('id')->pluck('skill_id')->all());
        $this->assertSame([$this->new['php'], $this->new['laravel'], $this->new['teamwork']], $skills);
        $notes = $this->entity($report)['notes'];
        $this->assertSame([1, 1], [$notes['skill-unmatched'], $notes['skill-link-incomplete']]);
        $this->assertSame([3, 2], [$notes['skill-text-matched'], $notes['skill-text-unmatched']]);
    }

    public function test_a_text_item_that_matches_two_skills_is_not_guessed(): void
    {
        DB::table('reference_skills')->insert(['name' => json_encode(['id' => 'Excel Lanjut', 'en' => 'Excel']), 'slug' => 'excel-advanced', 'kind' => 'hard', 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('reference_skills')->insert(['name' => json_encode(['id' => 'Excel Dasar', 'en' => 'Excel']), 'slug' => 'excel-basic', 'kind' => 'hard', 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->legacyJob(['hard_skill' => 'Excel, PHP']);

        $report = $this->import();

        $this->assertSame([$this->new['php']], array_map('intval', DB::table('job_job_skills')->pluck('skill_id')->all()));
        $this->assertSame(1, $this->entity($report)['notes']['skill-text-ambiguous']);
    }

    public function test_an_inactive_skill_is_not_matched_from_the_text(): void
    {
        DB::table('reference_skills')->where('slug', 'php')->update(['is_active' => false]);
        $this->legacyJob(['hard_skill' => 'PHP']);

        $this->import();

        $this->assertSame(0, DB::table('job_job_skills')->count());
    }

    public function test_more_than_fifteen_skills_are_cut_and_counted(): void
    {
        $job = $this->legacyJob(['hard_skill' => null]);

        for ($i = 1; $i <= 17; $i++) {
            $this->legacyMasterItem('skills', 100 + $i, ['en' => "extra-skill-{$i}"]);
            $this->referenceId('reference_skills', "extra-skill-{$i}", ['kind' => 'hard']);
            $this->legacyRow('job_skills', ['job_id' => $job, 'skill_id' => 100 + $i]);
        }

        $report = $this->import();

        $this->assertSame(15, DB::table('job_job_skills')->count());
        $this->assertSame(2, $this->entity($report)['notes']['skills-over-limit']);
    }

    public function test_benefits_with_no_counterpart_are_noted_and_duplicates_are_joined(): void
    {
        $job = $this->legacyJob();
        $this->legacyRow('job_benefit', ['job_id' => $job, 'benefit_id' => 1]);
        $this->legacyRow('job_benefit', ['job_id' => $job, 'benefit_id' => 1]);
        $this->legacyRow('job_benefit', ['job_id' => $job, 'benefit_id' => 77]);
        $this->legacyRow('job_benefit', ['job_id' => $job, 'benefit_id' => null]);

        $report = $this->import();

        $this->assertSame([$this->new['insurance']], array_map('intval', DB::table('job_job_benefits')->pluck('benefit_id')->all()));
        $notes = $this->entity($report)['notes'];
        $this->assertSame([1, 1], [$notes['benefit-unmatched'], $notes['benefit-link-incomplete']]);
    }

    // ----- salary, location and text -----

    public function test_the_salary_rules(): void
    {
        $this->legacyJob(['title' => 'Zero', 'min_salary' => 0, 'max_salary' => 0, 'salary_type_id' => 1]);
        $this->legacyJob(['title' => 'Swapped', 'min_salary' => 9000000, 'max_salary' => 4000000, 'salary_type_id' => 1]);
        $this->legacyJob(['title' => 'Only min', 'min_salary' => 7000000, 'max_salary' => 0, 'salary_type_id' => 1]);
        $this->legacyJob(['title' => 'Custom', 'salary_mode' => 'custom', 'custom_salary' => 'Negotiable', 'min_salary' => 1000, 'max_salary' => 2000, 'salary_type_id' => 1]);
        $this->legacyJob(['title' => 'No period', 'min_salary' => 3000000, 'max_salary' => 4000000, 'salary_type_id' => null]);
        $this->legacyJob(['title' => 'Hidden', 'show_salary' => null]);
        $this->legacyJob(['title' => 'Shown', 'show_salary' => '1']);
        $this->legacyJob(['title' => 'Off', 'show_salary' => '0']);

        $report = $this->import();

        $rows = DB::table('job_jobs')->orderBy('id')->get()->keyBy('title');
        $this->assertSame([null, null, null], [$rows['Zero']->salary_min, $rows['Zero']->salary_max, $rows['Zero']->salary_period_id]);
        $this->assertSame([4000000, 9000000], [(int) $rows['Swapped']->salary_min, (int) $rows['Swapped']->salary_max]);
        $this->assertSame([7000000, null, $this->new['period']], [(int) $rows['Only min']->salary_min, $rows['Only min']->salary_max, (int) $rows['Only min']->salary_period_id]);
        $this->assertSame([null, null, null], [$rows['Custom']->salary_min, $rows['Custom']->salary_max, $rows['Custom']->salary_period_id]);
        $this->assertSame([null, null, null], [$rows['No period']->salary_min, $rows['No period']->salary_max, $rows['No period']->salary_period_id]);
        $this->assertSame([0, 1, 0], [(int) $rows['Hidden']->salary_visible, (int) $rows['Shown']->salary_visible, (int) $rows['Off']->salary_visible]);
        $this->assertSame(['salary-swapped'], $this->flags($report));
        $this->assertSame(1, $this->entity($report)['notes']['salary-period-unmatched']);
    }

    public function test_the_location_comes_from_the_web_columns_then_from_the_api_columns(): void
    {
        $this->legacyJob(['title' => 'Web set', 'region' => 32, 'district' => 3273, 'subdistrict' => 327301, 'province_id' => 99, 'city_id' => 9999]);
        $this->legacyJob(['title' => 'API set', 'region' => null, 'district' => null, 'subdistrict' => null, 'province_id' => 32, 'city_id' => 3273]);
        $this->legacyJob(['title' => 'Broken web', 'region' => 99, 'district' => 3273, 'province_id' => 32, 'city_id' => 3273]);
        $this->legacyJob(['title' => 'Cut', 'region' => 32, 'district' => 9999, 'subdistrict' => 327301]);
        $this->legacyJob(['title' => 'Nothing', 'region' => null, 'district' => null, 'subdistrict' => null]);

        $report = $this->import();

        $rows = DB::table('job_jobs')->orderBy('id')->get()->keyBy('title');
        $levels = static fn (object $row): array => array_map(static fn ($id): ?int => $id === null ? null : (int) $id, [$row->province_id, $row->city_id, $row->subdistrict_id]);
        $this->assertSame([$this->chain['province_id'], $this->chain['city_id'], $this->chain['subdistrict_id']], $levels($rows['Web set']));
        $this->assertSame([$this->chain['province_id'], $this->chain['city_id'], null], $levels($rows['API set']));
        $this->assertSame([$this->chain['province_id'], $this->chain['city_id'], null], $levels($rows['Broken web']));
        $this->assertSame([$this->chain['province_id'], null, null], $levels($rows['Cut']));
        $this->assertSame([null, null, null], $levels($rows['Nothing']));
        $this->assertSame(2, $this->entity($report)['notes']['location-fallback']);
    }

    public function test_remote_jobs_and_the_description_conversion(): void
    {
        $this->legacyJob([
            'is_remote' => 1,
            'description' => '<h3>Role</h3><p>Build <strong>APIs</strong> for <em>everyone</em> &amp; more, all day long, with a <a href="https://example.test/x">link</a> and <a href="javascript:alert(1)" onclick="x()">a bad one</a>.</p><script>alert(1)</script><ul><li>One</li><li>Two</li></ul>',
        ]);

        $this->import();

        $row = $this->job();
        $this->assertSame('remote', $row->work_mode);
        $this->assertStringContainsString('### Role', $row->description);
        $this->assertStringContainsString('**APIs**', $row->description);
        $this->assertStringContainsString('*everyone* & more', $row->description);
        $this->assertStringContainsString('[link](https://example.test/x)', $row->description);
        $this->assertStringContainsString('a bad one', $row->description);
        $this->assertStringNotContainsString('javascript', $row->description);
        $this->assertStringNotContainsString('alert', $row->description);
        $this->assertStringContainsString("- One\n- Two", $row->description);
    }

    public function test_a_description_over_the_limit_is_cut_and_noted(): void
    {
        $this->legacyJob(['description' => '<p>'.str_repeat('word ', 5000).'</p>']);

        $report = $this->import();

        $this->assertSame(20000, mb_strlen($this->job()->description));
        $this->assertSame(1, $this->entity($report)['notes']['description-cut']);
    }

    // ----- running again, dry runs and company overrides -----

    public function test_a_second_run_adds_nothing(): void
    {
        $this->legacyJob();
        $this->import();

        $report = $this->import();

        $this->assertSame(1, DB::table('job_jobs')->count());
        $this->assertSame(1, DB::table('job_job_skills')->count());
        $this->assertSame([1, 0], [$this->entity($report)['read'], $this->entity($report)['imported']]);
        $this->assertSame(1, $this->entity($report)['notes']['already-imported']);
    }

    public function test_a_dry_run_reads_and_reports_but_writes_nothing(): void
    {
        $this->legacyJob();
        $this->legacyJob(['title' => 'Second']);

        $report = $this->import(false);

        $this->assertSame(0, DB::table('job_jobs')->count());
        $this->assertSame(0, DB::table('job_assignments')->count());
        $this->assertSame(0, DB::table('legacyimport_id_map')->where('entity', 'job')->count());
        $this->assertSame([2, 2], [$this->entity($report)['read'], $this->entity($report)['imported']]);
    }

    public function test_an_imported_draft_can_be_completed_and_published_through_the_company_api(): void
    {
        $this->legacyJob(['hard_skill' => null]);
        $this->import();
        $row = $this->job();
        $ownerId = (int) $row->created_by_user_id;
        $token = UserModel::query()->findOrFail($ownerId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->patchJson('/api/v1/company/jobs/'.$row->id, ['skill_ids' => [$this->new['php']]])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/company/jobs/'.$row->id.'/publish')->assertOk();

        $this->assertContains($this->job()->status, ['active', 'pending_review']);
    }
}
