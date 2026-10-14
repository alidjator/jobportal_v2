<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** Time is 2026-10-05 09:00 UTC, so yesterday (the last day an analysis may cover) is 2026-10-04 in Jakarta. */
final class AnalyticsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private const string RANGE = 'from=2026-10-01&to=2026-10-04';

    private int $nextApplicant = 9000;

    private int $nextAdmin = 0;

    private int $jobId;

    private int $otherJobId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->app->make(DefaultStageProvisioner::class)->provision($this->otherCompanyId);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');

        $this->jobId = $this->insertJob('Backend Developer', 'active', attributes: ['deadline' => '2026-12-31', 'published_at' => '2026-10-01 01:00:00']);
        $this->otherJobId = $this->insertJob('Other Developer', 'active', companyId: $this->otherCompanyId, attributes: ['deadline' => '2026-12-31', 'published_at' => '2026-10-01 01:00:00']);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function stageId(int $companyId, string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $companyId)->where('type', $type)->where('is_default', true)->value('id');
    }

    private function submitted(int $companyId, int $jobId, string $appliedAt, ?string $source, ?int $score): int
    {
        $userId = (int) UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Applicant', 'email' => 'applicant'.++$this->nextApplicant.'@example.test', 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00'])->id;
        $stage = $this->stageId($companyId, 'applied');

        $id = (int) DB::table('recruitment_applications')->insertGetId([
            'user_id' => $userId, 'job_id' => $jobId, 'company_id' => $companyId, 'stage_id' => $stage, 'stage_type' => 'applied', 'active_key' => $userId.'-'.$jobId,
            'applied_at' => $appliedAt, 'source' => $source, 'match_score' => $score, 'created_at' => $appliedAt, 'updated_at' => $appliedAt,
        ]);

        DB::table('recruitment_application_stage_history')->insert([
            'application_id' => $id, 'from_stage_id' => null, 'to_stage_id' => $stage, 'from_type' => null, 'to_type' => 'applied', 'actor_kind' => 'applicant', 'actor_id' => $userId,
            'rejection_reason' => null, 'note' => null, 'created_at' => $appliedAt,
        ]);

        return $id;
    }

    private function moved(int $applicationId, int $companyId, string $from, string $to, string $at, ?int $recruiterId, ?string $reason = null): void
    {
        DB::table('recruitment_application_stage_history')->insert([
            'application_id' => $applicationId, 'from_stage_id' => $this->stageId($companyId, $from), 'to_stage_id' => $this->stageId($companyId, $to), 'from_type' => $from, 'to_type' => $to,
            'actor_kind' => $recruiterId === null ? 'applicant' : 'recruiter', 'actor_id' => $recruiterId ?? 1, 'rejection_reason' => $reason, 'note' => null, 'created_at' => $at,
        ]);

        DB::table('recruitment_applications')->where('id', $applicationId)->update(['stage_id' => $this->stageId($companyId, $to), 'stage_type' => $to]);
    }

    /**
     * Company A: five applications that all reach screening, two of them interview, one of those gets an offer, one is rejected from screening; a sixth is rejected
     * from the first stage with no reason. Company B: one application that reaches screening.
     */
    private function scenario(): void
    {
        $apps = [];

        for ($i = 0; $i < 5; $i++) {
            $apps[$i] = $this->submitted($this->companyId, $this->jobId, '2026-10-01 10:00:00', 'search', 85);
            $this->moved($apps[$i], $this->companyId, 'applied', 'screening', '2026-10-02 10:00:00', $this->recruiterId);
        }

        foreach ([0, 1] as $i) {
            $this->moved($apps[$i], $this->companyId, 'screening', 'interview', '2026-10-04 10:00:00', $this->recruiterId);
        }

        $this->moved($apps[0], $this->companyId, 'interview', 'offer', '2026-10-04 12:00:00', $this->recruiterId);
        $this->moved($apps[2], $this->companyId, 'screening', 'rejected', '2026-10-03 10:00:00', $this->recruiterId, 'experience_insufficient');

        $sixth = $this->submitted($this->companyId, $this->jobId, '2026-10-02 10:00:00', 'direct', null);
        $this->moved($sixth, $this->companyId, 'applied', 'rejected', '2026-10-03 10:00:00', $this->secondRecruiterId);

        $foreign = $this->submitted($this->otherCompanyId, $this->otherJobId, '2026-10-01 10:00:00', 'search', 50);
        $this->moved($foreign, $this->otherCompanyId, 'applied', 'screening', '2026-10-02 10:00:00', $this->otherAdminId);

        Artisan::call('analytics:rollup', ['--from' => '2026-10-01', '--to' => '2026-10-04']);
        $this->app->make('cache')->flush();
    }

    private function company(string $report, string $query = self::RANGE, ?string $token = null): TestResponse
    {
        return $this->api('GET', "/company/analytics/{$report}?{$query}", [], $token ?? $this->adminToken);
    }

    /** @param  list<string>  $permissions */
    private function adminAccountToken(array $permissions): string
    {
        $admin = AdminModel::query()->create(['name' => 'Analyst', 'email' => 'analyst'.++$this->nextAdmin.'@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    private function platform(string $report, string $token, string $query = self::RANGE): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson("/api/v1/admin/analytics/{$report}?{$query}");
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function row(array $rows, string $column, string|int $value): array
    {
        foreach ($rows as $row) {
            if ($row[$column] === $value) {
                return $row;
            }
        }

        $this->fail("No row with {$column} = {$value}.");
    }

    // ----- access -----

    public function test_a_company_member_reads_the_company_analytics_with_the_analytics_permission(): void
    {
        $limited = $this->memberWith(['applications.view'])['token'];

        $this->company('funnel', token: $limited)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-permission-required')->assertJsonPath('permission', 'analytics.view');
        $this->flushHeaders();
        $this->company('funnel', token: $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-permission-required');
        $this->flushHeaders();
        $analyst = $this->memberWith(['applications.view', 'analytics.view'], 'analyst@example.test')['token'];
        $this->company('funnel', token: $analyst)->assertOk();
        $this->flushHeaders();
        $this->company('funnel', token: $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
        $this->company('funnel', token: $this->adminToken)->assertOk();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/company/analytics/funnel?'.self::RANGE)->assertStatus(401);
    }

    public function test_a_suspended_company_has_no_analytics(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->company('funnel')->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
    }

    public function test_the_platform_analytics_need_the_analytics_permission(): void
    {
        $this->platform('funnel', $this->adminAccountToken([]))->assertStatus(403);
        $this->platform('funnel', $this->adminAccountToken(['dashboard.view']))->assertStatus(403);
    }

    public function test_an_admin_with_the_permission_reads_the_platform_analytics(): void
    {
        $token = $this->adminAccountToken(['analytics.view']);

        $this->platform('funnel', $token)->assertOk()->assertJsonPath('data.report', 'funnel');
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/admin/analytics/funnel?'.self::RANGE)->assertStatus(401);
    }

    public function test_an_unknown_analysis_is_not_found(): void
    {
        $this->company('payroll')->assertStatus(404);
        $this->platform('payroll', $this->adminAccountToken(['analytics.view']))->assertStatus(404);
    }

    // ----- the range -----

    public function test_the_range_must_end_by_yesterday_and_stay_within_366_days(): void
    {
        $this->company('funnel', 'from=2026-10-01&to=2026-10-05')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-range');
        $this->company('funnel', 'from=2026-10-04&to=2026-10-01')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-range');
        $this->company('funnel', 'from=2025-10-03&to=2026-10-04')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-range');
        $this->company('funnel', 'from=2025-10-04&to=2026-10-04')->assertOk();
        $this->company('funnel', 'from=yesterday')->assertStatus(422)->assertJsonStructure(['errors' => ['from']]);
        $this->company('funnel', 'format=xml')->assertStatus(422)->assertJsonStructure(['errors' => ['format']]);
    }

    public function test_without_a_range_the_last_30_days_up_to_yesterday_are_shown(): void
    {
        $this->company('funnel', '')->assertOk()->assertJsonPath('data.from', '2026-09-05')->assertJsonPath('data.to', '2026-10-04')->assertJsonPath('data.min_group_size', 5);
    }

    // ----- the daily summaries -----

    public function test_a_day_is_rebuilt_to_the_same_rows_and_the_command_refuses_today(): void
    {
        $this->scenario();
        $before = [DB::table('recruitment_transitions_daily')->count(), DB::table('recruitment_applications_daily')->count()];

        $this->assertGreaterThan(0, $before[0]);
        Artisan::call('analytics:rollup', ['--from' => '2026-10-01', '--to' => '2026-10-04']);
        $this->assertSame($before, [DB::table('recruitment_transitions_daily')->count(), DB::table('recruitment_applications_daily')->count()]);

        $this->assertSame(1, Artisan::call('analytics:rollup', ['--to' => '2026-10-05']));
    }

    public function test_the_summaries_keep_counts_and_sums_by_jakarta_day(): void
    {
        $this->scenario();

        $this->assertSame(5, (int) DB::table('recruitment_applications_daily')->where('day', '2026-10-01')->where('company_id', $this->companyId)->sum('applications'));
        $this->assertSame(1, (int) DB::table('recruitment_applications_daily')->where('day', '2026-10-02')->where('company_id', $this->companyId)->sum('applications'));

        $screening = DB::table('recruitment_transitions_daily')->where('day', '2026-10-02')->where('company_id', $this->companyId)->where('to_type', 'screening')->first();
        $this->assertSame(5, (int) $screening->transitions);
        $this->assertSame(5 * 86400, (int) $screening->seconds_in_stage);
        $this->assertSame(5 * 86400, (int) $screening->seconds_since_applied);
        $this->assertSame((int) $this->recruiterId, (int) $screening->actor_id);
        $this->assertSame('strong', $screening->match_band);
    }

    public function test_the_backfill_starts_at_the_first_day_with_data(): void
    {
        $this->scenario();
        DB::table('recruitment_transitions_daily')->delete();
        DB::table('recruitment_applications_daily')->delete();

        $this->assertSame(0, Artisan::call('analytics:rollup', ['--backfill' => true, '--to' => '2026-10-04']));
        $this->assertGreaterThan(0, DB::table('recruitment_transitions_daily')->count());
        $this->assertSame(5, (int) DB::table('recruitment_applications_daily')->where('day', '2026-10-01')->where('company_id', $this->companyId)->sum('applications'));
    }

    // ----- funnel and speed -----

    public function test_the_funnel_counts_stages_with_averages_and_live_medians_and_hides_small_groups(): void
    {
        $this->scenario();

        $tables = $this->company('funnel')->assertOk()->json('data.tables');
        $this->assertSame(['stages', 'speed', 'offers', 'by_job', 'by_recruiter'], array_keys($tables));
        $this->assertSame(['stage', 'next_stage', 'entered', 'average_days_in_stage', 'median_days_in_stage', 'conversion'], $tables['stages']['columns']);

        $applied = $this->row($tables['stages']['rows'], 'stage', 'applied');
        $this->assertSame(6, $applied['entered']);
        $this->assertEquals(1.0, $applied['average_days_in_stage']);
        $this->assertEquals(1.0, $applied['median_days_in_stage']);
        $this->assertEquals(0.8333, $applied['conversion']);
        $this->assertFalse($applied['suppressed']);

        $screening = $this->row($tables['stages']['rows'], 'stage', 'screening');
        $this->assertSame(5, $screening['entered']);
        $this->assertEquals(1.67, $screening['average_days_in_stage']);
        $this->assertNull($screening['median_days_in_stage'], 'Three moves are too few for a median.');
        $this->assertEquals(0.4, $screening['conversion']);
        $this->assertSame('interview', $screening['next_stage'], 'An unused assessment stage is skipped.');

        foreach (['assessment', 'interview', 'offer', 'hired'] as $stage) {
            $row = $this->row($tables['stages']['rows'], 'stage', $stage);
            $this->assertTrue($row['suppressed'], $stage);
            $this->assertNull($row['entered'], $stage);
            $this->assertNull($row['conversion'], $stage);
        }

        $this->assertTrue($this->row($tables['speed']['rows'], 'milestone', 'offer')['suppressed']);
        $this->assertTrue($tables['offers']['rows'][0]['suppressed']);
        $this->assertNull($tables['offers']['rows'][0]['acceptance_rate']);
    }

    public function test_the_company_funnel_lists_its_jobs_and_recruiters_only(): void
    {
        $this->scenario();

        $tables = $this->company('funnel')->assertOk()->json('data.tables');

        $this->assertCount(1, $tables['by_job']['rows']);
        $job = $tables['by_job']['rows'][0];
        $this->assertSame($this->jobId, $job['job_id']);
        $this->assertSame('Backend Developer', $job['title']);
        $this->assertSame(6, $job['applications']);
        $this->assertSame(1, $job['offers']);
        $this->assertSame(0, $job['hired']);
        $this->assertSame(2, $job['rejected']);

        $members = $tables['by_recruiter']['rows'];
        $this->assertCount(2, $members);
        $first = $this->row($members, 'member_id', $this->recruiterId);
        $this->assertSame('Rina Rekruter', $first['name']);
        $this->assertSame(9, $first['moves']);
        $this->assertSame(8, $first['advanced']);
        $this->assertSame(1, $first['rejected']);
        $this->assertEquals(1.12, $first['average_days_in_stage']);

        $second = $this->row($members, 'member_id', $this->secondRecruiterId);
        $this->assertTrue($second['suppressed']);
        $this->assertNull($second['moves']);
    }

    public function test_the_platform_funnel_adds_up_every_company_and_has_no_job_or_recruiter_tables(): void
    {
        $this->scenario();

        $tables = $this->platform('funnel', $this->adminAccountToken(['analytics.view']))->assertOk()->json('data.tables');

        $this->assertSame(['stages', 'speed', 'offers'], array_keys($tables));
        $this->assertSame(7, $this->row($tables['stages']['rows'], 'stage', 'applied')['entered']);
        $this->assertSame(6, $this->row($tables['stages']['rows'], 'stage', 'screening')['entered']);
    }

    public function test_the_range_limits_what_is_counted(): void
    {
        $this->scenario();

        $tables = $this->company('funnel', 'from=2026-10-02&to=2026-10-04')->assertOk()->json('data.tables');

        $this->assertTrue($this->row($tables['stages']['rows'], 'stage', 'applied')['suppressed'], 'Only one application was submitted from 2 October on.');
    }

    // ----- rejections and sources -----

    public function test_the_rejection_reasons_and_sources_hide_small_groups(): void
    {
        $this->scenario();

        $tables = $this->company('rejections')->assertOk()->json('data.tables');
        $this->assertSame(['reasons', 'sources', 'worst_jobs'], array_keys($tables));

        $this->assertSame(['experience_insufficient', 'unspecified'], array_column($tables['reasons']['rows'], 'reason'));
        $this->assertSame([true, true], array_column($tables['reasons']['rows'], 'suppressed'));

        $search = $this->row($tables['sources']['rows'], 'source', 'search');
        $this->assertSame(5, $search['applications']);
        $this->assertSame(0, $search['hired']);
        $this->assertEquals(0.0, $search['hire_rate']);
        $this->assertTrue($this->row($tables['sources']['rows'], 'source', 'direct')['suppressed']);

        $worst = $tables['worst_jobs']['rows'];
        $this->assertSame([$this->jobId], array_column($worst, 'job_id'));
        $this->assertSame(6, $worst[0]['applications']);
    }

    // ----- matching quality -----

    public function test_the_match_bands_and_the_invitations_are_compared_with_the_outcomes(): void
    {
        $this->scenario();

        $tables = $this->company('matching')->assertOk()->json('data.tables');

        $this->assertSame(['unknown', 'low', 'fair', 'good', 'strong'], array_column($tables['bands']['rows'], 'band'));
        $strong = $this->row($tables['bands']['rows'], 'band', 'strong');
        $this->assertSame(5, $strong['applications']);
        $this->assertSame(5, $strong['advanced']);
        $this->assertSame(0, $strong['hired']);
        $this->assertSame(1, $strong['rejected']);
        $this->assertEquals(1.0, $strong['advance_rate']);
        $this->assertTrue($this->row($tables['bands']['rows'], 'band', 'unknown')['suppressed']);

        $this->assertTrue($this->row($tables['invitations']['rows'], 'origin', 'invitation')['suppressed']);
        $this->assertSame(6, $this->row($tables['invitations']['rows'], 'origin', 'other')['applications']);
    }

    // ----- job market -----

    public function test_the_market_groups_jobs_and_applicants_and_shows_salary_only_for_enough_jobs(): void
    {
        $category = $this->referenceId('reference_job_categories', 'analytics-engineering');
        $other = $this->referenceId('reference_job_categories', 'analytics-sales');
        $period = $this->referenceId('reference_salary_periods', 'analytics-month');

        $first = null;

        for ($i = 0; $i < 5; $i++) {
            $id = $this->insertJob('Market Job '.$i, 'active', attributes: [
                'category_id' => $category, 'published_at' => '2026-10-02 03:00:00', 'deadline' => '2026-12-31', 'salary_min' => 4_000_000 + $i * 1_000_000,
                'salary_max' => 6_000_000 + $i * 1_000_000, 'salary_period_id' => $period, 'salary_visible' => true,
            ]);
            $first ??= $id;
        }

        $this->insertJob('Market Sales', 'active', attributes: ['category_id' => $other, 'published_at' => '2026-10-02 03:00:00', 'deadline' => '2026-12-31']);
        $this->insertJob('Foreign Job', 'active', companyId: $this->otherCompanyId, attributes: ['category_id' => $category, 'published_at' => '2026-10-02 03:00:00', 'deadline' => '2026-12-31']);

        for ($i = 0; $i < 5; $i++) {
            $this->submitted($this->companyId, (int) $first, '2026-10-02 05:00:00', 'direct', null);
        }

        Artisan::call('analytics:rollup', ['--from' => '2026-10-01', '--to' => '2026-10-04']);
        $this->app->make('cache')->flush();

        $tables = $this->company('market')->assertOk()->json('data.tables');
        $this->assertSame(['categories', 'provinces', 'experience_levels', 'salary'], array_keys($tables));

        $engineering = $this->row($tables['categories']['rows'], 'category_id', $category);
        $this->assertSame('analytics-engineering', $engineering['name']);
        $this->assertSame(5, $engineering['jobs']);
        $this->assertSame(5, $engineering['applicants']);
        $this->assertEquals(1.0, $engineering['applicants_per_job']);

        $sales = $this->row($tables['categories']['rows'], 'category_id', $other);
        $this->assertSame(1, $sales['jobs'], 'The number of jobs is not about people.');
        $this->assertNull($sales['applicants']);
        $this->assertTrue($sales['suppressed']);

        $salary = $tables['salary']['rows'];
        $this->assertCount(1, $salary);
        $this->assertSame(5, $salary[0]['jobs']);
        $this->assertSame(6_000_000, $salary[0]['average_lower']);
        $this->assertSame(8_000_000, $salary[0]['average_upper']);
        $this->assertSame(4_000_000, $salary[0]['lowest']);
        $this->assertSame(10_000_000, $salary[0]['highest']);

        $platform = $this->platform('market', $this->adminAccountToken(['analytics.view']))->assertOk()->json('data.tables');
        $this->assertSame(6, $this->row($platform['categories']['rows'], 'category_id', $category)['jobs']);
    }

    public function test_four_jobs_with_a_salary_are_too_few_to_show_the_range(): void
    {
        $period = $this->referenceId('reference_salary_periods', 'analytics-month');

        for ($i = 0; $i < 4; $i++) {
            $this->insertJob('Pay Job '.$i, 'active', attributes: [
                'published_at' => '2026-10-02 03:00:00', 'deadline' => '2026-12-31', 'salary_min' => 5_000_000, 'salary_max' => 7_000_000, 'salary_period_id' => $period, 'salary_visible' => true,
            ]);
        }

        Artisan::call('analytics:rollup', ['--from' => '2026-10-01', '--to' => '2026-10-04']);

        $row = $this->company('market')->assertOk()->json('data.tables.salary.rows.0');
        $this->assertTrue($row['suppressed']);
        $this->assertNull($row['average_lower']);
        $this->assertNull($row['lowest']);
    }

    public function test_a_hidden_salary_is_left_out(): void
    {
        $period = $this->referenceId('reference_salary_periods', 'analytics-month');

        for ($i = 0; $i < 5; $i++) {
            $this->insertJob('Secret Job '.$i, 'active', attributes: [
                'published_at' => '2026-10-02 03:00:00', 'deadline' => '2026-12-31', 'salary_min' => 5_000_000, 'salary_max' => 7_000_000, 'salary_period_id' => $period, 'salary_visible' => false,
            ]);
        }

        Artisan::call('analytics:rollup', ['--from' => '2026-10-01', '--to' => '2026-10-04']);

        $this->assertSame([], $this->company('market')->assertOk()->json('data.tables.salary.rows'));
    }

    // ----- csv and the cache -----

    public function test_a_table_is_exported_as_csv_with_small_groups_written_as_less_than(): void
    {
        $this->scenario();

        $response = $this->company('funnel', self::RANGE.'&format=csv&table=stages')->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=analytics-funnel-stages-2026-10-01-2026-10-04.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFstage,next_stage,entered,average_days_in_stage,median_days_in_stage,conversion\r\n", $content);
        $this->assertStringContainsString("applied,screening,6,1,1,0.8333\r\n", $content);
        $this->assertStringContainsString("interview,offer,<5,,,\r\n", $content);
    }

    public function test_the_first_table_is_exported_when_none_is_named_and_an_unknown_table_is_not_found(): void
    {
        $this->scenario();

        $this->assertStringContainsString('stage,next_stage', (string) $this->company('funnel', self::RANGE.'&format=csv')->assertOk()->getContent());
        $this->company('funnel', self::RANGE.'&format=csv&table=payroll')->assertStatus(404);
    }

    public function test_an_analysis_is_kept_for_a_while(): void
    {
        $this->scenario();

        $first = $this->company('rejections')->assertOk()->json('data.tables.sources.rows.0.applications');
        $this->submitted($this->companyId, $this->jobId, '2026-10-01 11:00:00', 'search', 85);
        Artisan::call('analytics:rollup', ['--from' => '2026-10-01', '--to' => '2026-10-01']);

        $this->assertSame($first, $this->company('rejections')->assertOk()->json('data.tables.sources.rows.0.applications'));

        $this->app->make('cache')->flush();
        $this->assertSame($first + 1, $this->company('rejections')->assertOk()->json('data.tables.sources.rows.0.applications'));
    }

    public function test_the_smallest_group_is_an_admin_setting(): void
    {
        $this->scenario();
        $this->app->make('cache')->flush();

        $this->app->make(SettingsStore::class)->setGlobal('analytics.min_group_size', 2, null);
        $this->app->make('cache')->flush();

        $row = $this->row($this->company('funnel')->assertOk()->json('data.tables.stages.rows'), 'stage', 'interview');
        $this->assertFalse($row['suppressed']);
        $this->assertSame(2, $row['entered']);
    }

    // ----- time to fill -----

    private function hire(int $jobId, int $companyId, string $at): int
    {
        $application = $this->submitted($companyId, $jobId, '2026-09-01 00:00:00', 'direct', null);
        $this->moved($application, $companyId, 'applied', 'hired', $at, $this->recruiterId);

        return $application;
    }

    /**
     * Company A: job A (one position, live 10-01 01:00) is hired 10-03 01:00; job B (two positions, live 09-28 00:00) has hires at 10-02, 10-04 and 10-04 05:00, so its
     * second hire fills it on 10-04; job F is closed, live 09-01 and filled 09-20 (before the range); job C (two positions) has one hire, and another application that
     * was hired and moved back; job E is live with no hire; a draft was never published. Company B: one job filled 10-02 01:00 after a day.
     */
    private function fillScenario(): void
    {
        $this->hire($this->jobId, $this->companyId, '2026-10-03 01:00:00');

        $b = $this->insertJob('Job B', 'active', [$this->recruiterId], attributes: ['vacancies' => 2, 'published_at' => '2026-09-28 00:00:00']);

        foreach (['2026-10-02 00:00:00', '2026-10-04 00:00:00', '2026-10-04 05:00:00'] as $at) {
            $this->hire($b, $this->companyId, $at);
        }

        $f = $this->insertJob('Job F', 'closed', attributes: ['published_at' => '2026-09-01 00:00:00']);
        $this->hire($f, $this->companyId, '2026-09-20 00:00:00');

        $c = $this->insertJob('Job C', 'active', [$this->secondRecruiterId], attributes: ['vacancies' => 2, 'published_at' => '2026-10-02 00:00:00']);
        $this->hire($c, $this->companyId, '2026-10-03 00:00:00');
        $this->moved($this->hire($c, $this->companyId, '2026-10-03 02:00:00'), $this->companyId, 'hired', 'interview', '2026-10-03 03:00:00', $this->recruiterId);

        $this->insertJob('Job E', 'active', attributes: ['published_at' => '2026-09-25 00:00:00']);
        $this->insertJob('Never published', 'draft');

        $foreign = $this->insertJob('Job H', 'active', companyId: $this->otherCompanyId, attributes: ['published_at' => '2026-10-01 01:00:00']);
        $this->hire($foreign, $this->otherCompanyId, '2026-10-02 01:00:00');

        $this->app->make('cache')->flush();
    }

    public function test_the_fill_report_measures_the_days_from_going_live_to_the_last_hire(): void
    {
        $this->fillScenario();

        $tables = $this->company('fill')->assertOk()->json('data.tables');
        $this->assertSame(['summary', 'by_category', 'by_job', 'by_recruiter', 'open'], array_keys($tables));

        $summary = $tables['summary']['rows'][0];
        $this->assertSame(2, $summary['filled_jobs']);
        $this->assertEquals(4.0, $summary['average_days']);
        $this->assertNull($summary['median_days'], 'Two jobs are too few for a median.');
        $this->assertFalse($summary['suppressed']);

        $byJob = $tables['by_job']['rows'];
        $this->assertSame(['Job B', 'Backend Developer'], array_column($byJob, 'title'), 'The latest fill comes first.');
        $this->assertEquals(['published' => '2026-09-28', 'filled' => '2026-10-04', 'days' => 6, 'vacancies' => 2, 'hires' => 3], array_intersect_key($byJob[0], array_flip(['published', 'filled', 'days', 'vacancies', 'hires'])));
        $this->assertEquals(2.0, $byJob[1]['days']);
        $this->assertNull($byJob[1]['vacancies']);

        $this->assertSame(2, $tables['by_category']['rows'][0]['filled_jobs']);

        $recruiters = $tables['by_recruiter']['rows'];
        $this->assertEqualsCanonicalizing([$this->adminId, $this->recruiterId], array_column($recruiters, 'member_id'));
        $this->assertSame([1, 1], array_column($recruiters, 'filled_jobs'));
        $this->assertNotNull($recruiters[0]['name']);

        $open = $tables['open']['rows'];
        $this->assertSame(['Job E', 'Job C'], array_column($open, 'title'), 'The longest open comes first.');
        $this->assertEquals(10.4, $open[0]['days_open']);
        $this->assertSame(0, $open[0]['hires']);
        $this->assertEquals(3.4, $open[1]['days_open']);
        $this->assertSame(1, $open[1]['hires'], 'An application moved back from the hired stage is not a hire.');
        $this->assertSame(2, $open[1]['vacancies']);
    }

    public function test_the_median_shows_once_enough_jobs_are_filled(): void
    {
        $this->fillScenario();
        $this->app->make(SettingsStore::class)->setGlobal('analytics.min_group_size', 2, null);
        $this->app->make('cache')->flush();

        $this->assertEquals(4.0, $this->company('fill')->assertOk()->json('data.tables.summary.rows.0.median_days'));
    }

    public function test_a_job_counts_in_the_range_of_the_day_it_was_filled(): void
    {
        $this->fillScenario();

        $this->assertSame(3, $this->company('fill', 'from=2026-09-01&to=2026-10-04')->assertOk()->json('data.tables.summary.rows.0.filled_jobs'));
        $this->assertSame(1, $this->company('fill', 'from=2026-10-04&to=2026-10-04')->assertOk()->json('data.tables.summary.rows.0.filled_jobs'));
        $this->assertSame(0, $this->company('fill', 'from=2026-10-01&to=2026-10-02')->assertOk()->json('data.tables.summary.rows.0.filled_jobs'));
    }

    public function test_the_platform_fill_report_adds_up_the_companies_and_has_no_job_tables(): void
    {
        $this->fillScenario();

        $tables = $this->platform('fill', $this->adminAccountToken(['analytics.view']))->assertOk()->json('data.tables');
        $this->assertSame(['summary', 'by_category', 'by_company'], array_keys($tables));
        $this->assertSame(3, $tables['summary']['rows'][0]['filled_jobs']);
        $this->assertEquals(3.0, $tables['summary']['rows'][0]['average_days']);
        $this->assertSame([2, 1], array_column($tables['by_company']['rows'], 'filled_jobs'));
        $this->assertSame($this->companyId, $tables['by_company']['rows'][0]['company_id']);
        $this->assertNotNull($tables['by_company']['rows'][0]['company']);
    }

    public function test_the_fill_report_needs_the_analytics_permission_and_exports_a_table(): void
    {
        $this->fillScenario();

        $this->company('fill', token: $this->recruiterToken)->assertStatus(403)->assertJsonPath('permission', 'analytics.view');

        $csv = $this->company('fill', self::RANGE.'&format=csv&table=open')->assertOk();
        $this->assertStringContainsString('Job E', $csv->getContent());
    }

    public function test_the_dashboard_card_gives_the_median_of_the_last_90_days_and_the_jobs_not_filled(): void
    {
        $this->fillScenario();

        $card = $this->api('GET', '/company/dashboard', [], $this->adminToken)->assertOk()->json('data.cards.time_to_fill');
        $this->assertSame(['period_days' => 90, 'filled_jobs' => 3, 'median_days' => null, 'open_jobs' => 2], $card);

        $this->app->make(SettingsStore::class)->setGlobal('analytics.min_group_size', 2, null);
        $this->app->make('cache')->flush();
        $this->assertEquals(6.0, $this->api('GET', '/company/dashboard', [], $this->adminToken)->assertOk()->json('data.cards.time_to_fill.median_days'));

        $this->flushHeaders();
        $this->assertArrayNotHasKey('time_to_fill', $this->api('GET', '/company/dashboard', [], $this->recruiterToken)->assertOk()->json('data.cards'));
    }
}
