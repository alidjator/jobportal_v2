<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Job\Application\Insight\SalaryInsightReader;
use Src\Job\Infrastructure\Insight\CachedSalaryInsightReader;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsJobFixtures;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class SalaryInsightTest extends FeatureTestCase
{
    use BuildsJobFixtures;
    use CreatesReferenceRows;

    private int $role;

    private int $category;

    private int $level;

    private int $monthly;

    /** @var array{province_id: int, city_id: int, subdistrict_id: int, village_id: int, postal_code_id: int} */
    private array $place;

    /** @var list<int> */
    private array $companies = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
        $this->role = $this->referenceId('reference_job_roles', 'backend-developer');
        $this->category = $this->referenceId('reference_job_categories', 'it');
        $this->level = $this->referenceId('reference_experience_levels', 'mid');
        $this->monthly = $this->referenceId('reference_salary_periods', 'monthly');
        $this->place = $this->locationChain();
        $this->companies = [$this->companyId, $this->otherCompanyId, $this->companyRowId('pt-ketiga')];
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    private function insight(string $query): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->getJson('/api/v1/salary-insight?'.$query);
    }

    /** @param  array<string, mixed>  $attributes */
    private function job(?int $min, ?int $max, int $company, array $attributes = []): int
    {
        return $this->insertJob('Backend Developer', 'active', null, $company, [
            'role_id' => $this->role, 'category_id' => $this->category, 'experience_level_id' => $this->level, 'province_id' => $this->place['province_id'], 'city_id' => $this->place['city_id'],
            'salary_min' => $min, 'salary_max' => $max, 'salary_period_id' => $this->monthly, 'salary_visible' => true, 'deadline' => '2026-12-31', 'published_at' => '2026-09-01 08:00:00', ...$attributes,
        ]);
    }

    /**
     * Five jobs (the default minimum) of three companies, with midpoints 8, 9, 10, 11 and 12 million.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function fiveJobs(array $attributes = []): void
    {
        foreach ([8, 9, 10, 11, 12] as $index => $million) {
            $this->job($million * 1_000_000 - 500_000, $million * 1_000_000 + 500_000, $this->companies[$index % 3], $attributes);
        }
    }

    public function test_the_median_and_the_spread_come_from_the_midpoints(): void
    {
        $this->fiveJobs();

        $this->insight("role_id={$this->role}")->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.period', 'monthly')
            ->assertJsonPath('data.window_months', 12)
            ->assertJsonPath('data.role.id', $this->role)
            ->assertJsonPath('data.scope.level', 'role')
            ->assertJsonPath('data.widened', false)
            ->assertJsonPath('data.jobs', 5)
            ->assertJsonPath('data.p25', 9_000_000)
            ->assertJsonPath('data.median', 10_000_000)
            ->assertJsonPath('data.p75', 11_000_000);
    }

    public function test_a_one_sided_range_counts_by_its_one_end(): void
    {
        foreach ([8, 9, 10, 11, 12] as $index => $million) {
            $this->job($index % 2 === 0 ? $million * 1_000_000 : null, $index % 2 === 0 ? null : $million * 1_000_000, $this->companies[$index % 3]);
        }

        $this->insight("role_id={$this->role}")->assertOk()->assertJsonPath('data.median', 10_000_000);
    }

    public function test_a_group_with_too_few_jobs_or_companies_shows_nothing_and_no_count(): void
    {
        foreach ([8, 9, 10, 11] as $million) {
            $this->job($million * 1_000_000, null, $this->companies[0]);
        }

        $response = $this->insight("role_id={$this->role}")->assertOk()->assertJsonPath('data.available', false);
        $this->assertArrayNotHasKey('jobs', $response->json('data'));
        $this->assertArrayNotHasKey('median', $response->json('data'));

        // A fifth job is not enough while one company stands behind all of them, and is once three companies do.
        $this->job(12_000_000, null, $this->companies[0]);
        Cache::flush();
        $this->insight("role_id={$this->role}")->assertJsonPath('data.available', false);

        DB::table('job_jobs')->where('salary_min', 8_000_000)->update(['company_id' => $this->companies[1]]);
        DB::table('job_jobs')->where('salary_min', 9_000_000)->update(['company_id' => $this->companies[2]]);
        Cache::flush();
        $this->insight("role_id={$this->role}")->assertJsonPath('data.available', true)->assertJsonPath('data.jobs', 5);
    }

    public function test_only_visible_monthly_published_jobs_of_the_last_twelve_months_count(): void
    {
        $this->fiveJobs();
        $hourly = $this->referenceId('reference_salary_periods', 'hourly');
        $otherRole = $this->referenceId('reference_job_roles', 'designer');

        $this->job(90_000_000, null, $this->companies[0], ['salary_visible' => false]);
        $this->job(90_000_000, null, $this->companies[1], ['salary_period_id' => $hourly]);
        $this->job(90_000_000, null, $this->companies[2], ['published_at' => '2025-09-01 08:00:00']);
        $this->job(90_000_000, null, $this->companies[0], ['role_id' => $otherRole]);
        $this->job(90_000_000, null, $this->companies[1], ['salary_min' => null, 'salary_max' => null]);
        foreach (['draft', 'pending_review', 'rejected', 'taken_down'] as $status) {
            $this->job(90_000_000, null, $this->companies[2], ['status' => $status]);
        }

        $this->insight("role_id={$this->role}")->assertOk()->assertJsonPath('data.jobs', 5)->assertJsonPath('data.median', 10_000_000);
    }

    public function test_closed_and_expired_jobs_count_as_real_offers(): void
    {
        foreach (['active', 'closed', 'expired', 'closed', 'expired'] as $index => $status) {
            $this->job(10_000_000, null, $this->companies[$index % 3], ['status' => $status]);
        }

        $this->insight("role_id={$this->role}")->assertOk()->assertJsonPath('data.jobs', 5);
    }

    public function test_jobs_of_a_company_the_public_cannot_see_are_left_out(): void
    {
        $this->fiveJobs();
        $this->assertSame(5, $this->insight("role_id={$this->role}")->json('data.jobs'));

        DB::table('company_companies')->where('id', $this->companies[0])->update(['status' => 'suspended']);
        Cache::flush();

        $this->insight("role_id={$this->role}")->assertOk()->assertJsonPath('data.available', false);
    }

    public function test_a_group_that_is_too_small_widens_one_step_at_a_time(): void
    {
        // Five jobs in Bandung at the mid level; the visitor asks for a senior in Bandung.
        $this->fiveJobs();
        $senior = $this->referenceId('reference_experience_levels', 'senior');
        $cityQuery = "role_id={$this->role}&city_id={$this->place['city_id']}&experience_level_id={$senior}";

        $this->insight($cityQuery)->assertOk()
            ->assertJsonPath('data.widened', true)
            ->assertJsonPath('data.scope.city.id', $this->place['city_id'])
            ->assertJsonPath('data.scope.province.id', $this->place['province_id'])
            ->assertJsonPath('data.scope.experience_level', null);

        // Another city of the same province has too few jobs: the answer widens to the province.
        $other = DB::table('reference_cities')->insertGetId(['province_id' => $this->place['province_id'], 'bps_code' => '3204', 'name' => 'BANDUNG BARAT', 'type' => 'regency']);
        $this->insight("role_id={$this->role}&city_id={$other}")->assertOk()
            ->assertJsonPath('data.widened', true)
            ->assertJsonPath('data.scope.city', null)
            ->assertJsonPath('data.scope.province.id', $this->place['province_id']);

        // Another province: the whole country.
        $province = DB::table('reference_provinces')->insertGetId(['bps_code' => '35', 'name' => 'JAWA TIMUR']);
        $this->insight("role_id={$this->role}&province_id={$province}")->assertOk()
            ->assertJsonPath('data.widened', true)
            ->assertJsonPath('data.scope.province', null);

        // What was asked and found is not widened.
        $this->insight("role_id={$this->role}&city_id={$this->place['city_id']}&experience_level_id={$this->level}")->assertOk()->assertJsonPath('data.widened', false);
    }

    public function test_the_last_step_is_the_category_and_only_when_one_was_given(): void
    {
        // Five jobs of the category, none of them with the asked role.
        $this->fiveJobs(['role_id' => $this->referenceId('reference_job_roles', 'designer')]);

        $this->insight("role_id={$this->role}")->assertOk()->assertJsonPath('data.available', false);

        $this->insight("role_id={$this->role}&category_id={$this->category}")->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.widened', true)
            ->assertJsonPath('data.scope.level', 'category')
            ->assertJsonPath('data.scope.category.id', $this->category)
            ->assertJsonPath('data.scope.province', null)
            ->assertJsonPath('data.median', 10_000_000);
    }

    public function test_the_minimum_is_the_admin_setting(): void
    {
        foreach ([8, 9, 10] as $index => $million) {
            $this->job($million * 1_000_000, null, $this->companies[$index]);
        }

        $this->insight("role_id={$this->role}")->assertJsonPath('data.available', false);

        $this->app->make(SettingsStore::class)->setGlobal('analytics.min_group_size', 3, null);
        Cache::flush();

        $this->insight("role_id={$this->role}")->assertJsonPath('data.available', true)->assertJsonPath('data.jobs', 3);
    }

    public function test_ids_that_do_not_exist_and_a_city_outside_its_province_are_refused(): void
    {
        $this->insight('')->assertStatus(422)->assertJsonValidationErrors(['role_id']);
        $this->insight('role_id=999999')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-reference')->assertJsonPath('errors.role_id.0', 'This value does not exist or is not active.');
        $this->insight("role_id={$this->role}&experience_level_id=999999&category_id=999999")->assertStatus(422)->assertJsonStructure(['errors' => ['experience_level_id', 'category_id']]);

        $province = DB::table('reference_provinces')->insertGetId(['bps_code' => '35', 'name' => 'JAWA TIMUR']);
        $this->insight("role_id={$this->role}&city_id={$this->place['city_id']}&province_id={$province}")->assertStatus(422)->assertJsonStructure(['errors' => ['city_id']]);
        $this->insight("role_id={$this->role}&city_id=999999")->assertStatus(422)->assertJsonStructure(['errors' => ['city_id']]);
        $this->insight("role_id={$this->role}&province_id=999999")->assertStatus(422)->assertJsonStructure(['errors' => ['province_id']]);
    }

    public function test_the_names_follow_the_request_language(): void
    {
        DB::table('reference_job_roles')->where('id', $this->role)->update(['name' => json_encode(['id' => 'Pengembang Backend', 'en' => 'Backend Developer'])]);
        $this->fiveJobs();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/salary-insight?role_id={$this->role}", ['Accept-Language' => 'id'])->assertJsonPath('data.role.name', 'Pengembang Backend');
        $this->getJson("/api/v1/salary-insight?role_id={$this->role}", ['Accept-Language' => 'en'])->assertJsonPath('data.role.name', 'Backend Developer');
    }

    public function test_the_figures_are_kept_for_an_hour(): void
    {
        $this->fiveJobs();
        $this->assertSame(5, $this->insight("role_id={$this->role}")->json('data.jobs'));

        $this->job(30_000_000, null, $this->companies[0]);
        $this->assertSame(5, $this->insight("role_id={$this->role}")->json('data.jobs'), 'the cached figures are served');

        Cache::flush();
        $this->assertSame(6, $this->insight("role_id={$this->role}")->json('data.jobs'));
    }

    public function test_the_answer_is_public_and_cacheable_without_a_token(): void
    {
        $this->fiveJobs();

        $response = $this->insight("role_id={$this->role}")->assertOk();
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_each_client_address_is_limited_to_sixty_a_minute(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->insight("role_id={$this->role}")->assertOk();
        }

        $this->insight("role_id={$this->role}")->assertStatus(429);
    }

    public function test_the_reader_is_the_cached_one(): void
    {
        $this->assertInstanceOf(CachedSalaryInsightReader::class, $this->app->make(SalaryInsightReader::class));
    }
}
