<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Job\Infrastructure\Persistence\JobSearchVectors;
use Src\Shared\Application\References\TagRegistry;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class JobSearchTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /** @param  array<string, string>  $headers */
    private function search(string $query = '', array $headers = []): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->getJson('/api/v1/jobs'.($query === '' ? '' : '?'.$query), $headers);
    }

    /** @return list<string> */
    private function titles(string $query = ''): array
    {
        return array_column($this->search($query)->assertOk()->json('data'), 'title');
    }

    /**
     * A live job, inserted directly: active, deadline in the future, published.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $skillIds
     * @param  list<string>  $tags
     */
    private function live(string $title, array $attributes = [], array $skillIds = [], array $tags = [], ?int $companyId = null): int
    {
        $id = $this->insertJob($title, 'active', [$this->adminId], $companyId, ['deadline' => '2026-12-31', 'published_at' => '2026-10-01 08:00:00', ...$attributes]);

        foreach ($skillIds as $skillId) {
            DB::table('job_job_skills')->insert(['job_id' => $id, 'skill_id' => $skillId]);
        }

        foreach ($this->app->make(TagRegistry::class)->idsFor($tags) as $tagId) {
            DB::table('job_job_tags')->insert(['job_id' => $id, 'tag_id' => $tagId]);
        }

        $this->app->make(JobSearchVectors::class)->refresh($id);

        return $id;
    }

    // ----- what is searchable -----

    public function test_only_live_jobs_of_public_companies_are_found(): void
    {
        $this->live('Visible');
        $this->live('Deadline today', ['deadline' => '2026-10-05']);
        $this->live('Deadline over', ['deadline' => '2026-10-04']);
        foreach (['draft', 'pending_review', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $this->insertJob("Job {$status}", $status, attributes: ['deadline' => '2026-12-31', 'published_at' => '2026-10-01 08:00:00']);
        }
        $this->live('Suspended company', companyId: $this->otherCompanyId);
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['status' => 'suspended']);
        $hidden = $this->companyRowId('pt-hidden');
        DB::table('company_companies')->where('id', $hidden)->update(['is_visible' => false]);
        $this->live('Hidden company', companyId: $hidden);

        $response = $this->search()->assertOk();

        $this->assertEqualsCanonicalizing(['Visible', 'Deadline today'], array_column($response->json('data'), 'title'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_a_company_that_becomes_public_again_brings_its_jobs_back(): void
    {
        $this->live('Theirs', companyId: $this->otherCompanyId);
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['status' => 'suspended']);
        $this->assertSame([], $this->titles());

        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['status' => 'active']);
        $this->assertSame(['Theirs'], $this->titles());
    }

    public function test_the_deadline_day_ends_at_midnight_in_jakarta(): void
    {
        $this->live('Edge', ['deadline' => '2026-10-05']);

        $this->assertSame(['Edge'], $this->titles());

        Carbon::setTestNow('2026-10-05 17:00:00');
        $this->assertSame([], $this->titles());
    }

    // ----- a row -----

    public function test_a_row_shows_what_is_needed_to_choose_a_job(): void
    {
        $chain = $this->locationChain();
        $type = $this->referenceId('reference_job_types', 'full-time');
        $level = $this->referenceId('reference_job_levels', 'senior');
        $period = $this->referenceId('reference_salary_periods', 'monthly');
        DB::table('company_companies')->where('id', $this->companyId)->update(['verified_at' => '2026-10-02 08:00:00', 'logo_path' => 'company/logos/abc123.png']);
        $id = $this->live('Backend Developer', [
            'job_type_id' => $type, 'job_level_id' => $level, 'work_mode' => 'hybrid', 'province_id' => $chain['province_id'], 'city_id' => $chain['city_id'],
            'salary_min' => 10000000, 'salary_max' => 15000000, 'salary_period_id' => $period, 'salary_visible' => true, 'description' => 'A long text that must not show.',
        ]);

        $row = $this->search()->assertOk()->json('data.0');

        $this->assertSame($id, $row['id']);
        $this->assertSame("backend-developer-{$id}", $row['slug']);
        $this->assertSame('Backend Developer', $row['title']);
        $this->assertSame(['id', 'name', 'slug', 'logo_url', 'verified'], array_keys($row['company']));
        $this->assertSame([$this->companyId, 'PT-MAJU', 'pt-maju', true], [$row['company']['id'], $row['company']['name'], $row['company']['slug'], $row['company']['verified']]);
        $this->assertStringEndsWith('/api/v1/companies/pt-maju/logo?v=abc123', $row['company']['logo_url']);
        $this->assertSame([['id' => null, 'name' => null, 'type' => null, 'city' => 'BANDUNG', 'province' => 'JAWA BARAT']], $row['locations']);
        $this->assertSame(['id' => $type, 'name' => 'full-time'], $row['job_type']);
        $this->assertSame(['id' => $level, 'name' => 'senior'], $row['job_level']);
        $this->assertSame('hybrid', $row['work_mode']);
        $this->assertSame(['min' => 10000000, 'max' => 15000000, 'period' => ['id' => $period, 'name' => 'monthly']], $row['salary']);
        $this->assertSame(['2026-12-31', '2026-10-01T08:00:00+00:00'], [$row['deadline'], $row['published_at']]);
        $this->assertSame(
            ['id', 'slug', 'title', 'company', 'locations', 'job_type', 'job_level', 'work_mode', 'salary', 'deadline', 'published_at'],
            array_keys($row),
        );
    }

    public function test_a_row_without_optional_fields_has_nulls_and_a_hidden_salary_is_not_shown(): void
    {
        $period = $this->referenceId('reference_salary_periods', 'monthly');
        $this->live('Bare');
        $this->live('Hidden pay', ['salary_min' => 5000000, 'salary_period_id' => $period, 'salary_visible' => false]);

        $rows = collect($this->search()->assertOk()->json('data'))->keyBy('title');

        $this->assertSame([null, null, null, null, []], [$rows['Bare']['job_type'], $rows['Bare']['job_level'], $rows['Bare']['work_mode'], $rows['Bare']['salary'], $rows['Bare']['locations']]);
        $this->assertNull($rows['Hidden pay']['salary']);
        $this->assertStringNotContainsString('5000000', (string) json_encode($rows['Hidden pay']));
    }

    public function test_names_follow_the_request_language(): void
    {
        $type = $this->referenceId('reference_job_types', 'full-time');
        DB::table('reference_job_types')->where('id', $type)->update(['name' => json_encode(['id' => 'Penuh Waktu', 'en' => 'Full Time'])]);
        $this->live('Backend', ['job_type_id' => $type]);

        $this->assertSame('Penuh Waktu', $this->search('', ['Accept-Language' => 'id'])->json('data.0.job_type.name'));
        $this->assertSame('Full Time', $this->search('', ['Accept-Language' => 'en'])->json('data.0.job_type.name'));
    }

    // ----- filters -----

    public function test_each_master_filter_matches_one_value_or_any_of_several(): void
    {
        $category = [$this->referenceId('reference_job_categories', 'engineering'), $this->referenceId('reference_job_categories', 'design')];
        $role = [$this->referenceId('reference_job_roles', 'backend'), $this->referenceId('reference_job_roles', 'frontend')];
        $type = [$this->referenceId('reference_job_types', 'full-time'), $this->referenceId('reference_job_types', 'part-time')];
        $level = [$this->referenceId('reference_job_levels', 'junior'), $this->referenceId('reference_job_levels', 'senior')];
        $experience = [$this->referenceId('reference_experience_levels', 'one-year'), $this->referenceId('reference_experience_levels', 'five-years')];
        $education = [$this->referenceId('reference_education_levels', 'diploma', ['rank' => 3]), $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5])];
        $workTime = [$this->referenceId('reference_work_times', 'day'), $this->referenceId('reference_work_times', 'night')];

        $this->live('A', ['category_id' => $category[0], 'role_id' => $role[0], 'job_type_id' => $type[0], 'job_level_id' => $level[0], 'experience_level_id' => $experience[0], 'education_level_id' => $education[0], 'work_time_id' => $workTime[0]]);
        $this->live('B', ['category_id' => $category[1], 'role_id' => $role[1], 'job_type_id' => $type[1], 'job_level_id' => $level[1], 'experience_level_id' => $experience[1], 'education_level_id' => $education[1], 'work_time_id' => $workTime[1]]);
        $this->live('C');

        foreach (['category_id' => $category, 'role_id' => $role, 'job_type_id' => $type, 'job_level_id' => $level, 'experience_level_id' => $experience, 'education_level_id' => $education, 'work_time_id' => $workTime] as $filter => $ids) {
            $this->assertSame(['A'], $this->titles("filter[{$filter}]={$ids[0]}"), $filter);
            $this->assertSame(['B'], $this->titles("filter[{$filter}]={$ids[1]}"), $filter);
            $this->assertEqualsCanonicalizing(['A', 'B'], $this->titles("filter[{$filter}]={$ids[0]},{$ids[1]}"), $filter);
        }
    }

    public function test_the_filters_are_combined_with_and_and_an_empty_result_is_a_valid_answer(): void
    {
        $engineering = $this->referenceId('reference_job_categories', 'engineering');
        $senior = $this->referenceId('reference_job_levels', 'senior');
        $junior = $this->referenceId('reference_job_levels', 'junior');
        $this->live('A', ['category_id' => $engineering, 'job_level_id' => $senior]);
        $this->live('B', ['category_id' => $engineering, 'job_level_id' => $junior]);

        $this->assertSame(['A'], $this->titles("filter[category_id]={$engineering}&filter[job_level_id]={$senior}"));
        $this->assertSame([], $this->titles('filter[category_id]=999999'));
        $this->assertSame(0, $this->search('filter[category_id]=999999')->json('meta.total'));
    }

    public function test_work_mode_province_city_skill_and_company_filters(): void
    {
        $chain = $this->locationChain();
        $otherProvince = DB::table('reference_provinces')->insertGetId(['bps_code' => '31', 'name' => 'DKI JAKARTA']);
        $otherCity = DB::table('reference_cities')->insertGetId(['province_id' => $otherProvince, 'bps_code' => '3171', 'name' => 'JAKARTA PUSAT', 'type' => 'city']);
        $php = $this->skillId('php');
        $go = $this->skillId('go');

        $this->live('Bandung onsite', ['work_mode' => 'onsite', 'province_id' => $chain['province_id'], 'city_id' => $chain['city_id']], [$php]);
        $this->live('Jakarta hybrid', ['work_mode' => 'hybrid', 'province_id' => $otherProvince, 'city_id' => $otherCity], [$php, $go]);
        $this->live('Remote', ['work_mode' => 'remote'], [$go]);
        $this->live('Theirs', ['work_mode' => 'onsite'], [], companyId: $this->otherCompanyId);

        $this->assertEqualsCanonicalizing(['Jakarta hybrid'], $this->titles('filter[work_mode]=hybrid'));
        $this->assertEqualsCanonicalizing(['Bandung onsite', 'Jakarta hybrid'], $this->titles('filter[work_mode]=onsite,hybrid&filter[company_id]='.$this->companyId));
        $this->assertSame(['Bandung onsite'], $this->titles("filter[province_id]={$chain['province_id']}"));
        $this->assertSame(['Jakarta hybrid'], $this->titles("filter[city_id]={$otherCity}"));
        $this->assertEqualsCanonicalizing(['Bandung onsite', 'Jakarta hybrid'], $this->titles("filter[province_id]={$chain['province_id']},{$otherProvince}"));
        $this->assertEqualsCanonicalizing(['Bandung onsite', 'Jakarta hybrid'], $this->titles("filter[skill_id]={$php}"));
        $this->assertEqualsCanonicalizing(['Bandung onsite', 'Jakarta hybrid', 'Remote'], $this->titles("filter[skill_id]={$php},{$go}"));
        $this->assertSame(['Jakarta hybrid'], $this->titles("filter[skill_id]={$php}&filter[city_id]={$otherCity}"));
        $this->assertSame(['Theirs'], $this->titles('filter[company_id]='.$this->otherCompanyId));
    }

    public function test_a_hidden_company_cannot_be_reached_through_its_id_either(): void
    {
        $this->live('Theirs', companyId: $this->otherCompanyId);
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['is_visible' => false]);

        $this->assertSame([], $this->titles('filter[company_id]='.$this->otherCompanyId));
    }

    // ----- keyword -----

    public function test_the_keyword_matches_whole_words_and_the_start_of_the_last_one_whatever_the_letter_case(): void
    {
        $this->live('Backend Developer');
        $this->live('Data Analyst');

        $this->assertSame(['Backend Developer'], $this->titles('filter[q]=develo'));
        $this->assertSame(['Backend Developer'], $this->titles('filter[q]=BACKEND'));
        $this->assertSame(['Data Analyst'], $this->titles('filter[q]=data analy'));
        $this->assertSame([], $this->titles('filter[q]=dev+backend'), 'a word in the middle is a whole word, not a prefix');
        $this->assertSame([], $this->titles('filter[q]=nothing'));
    }

    public function test_the_keyword_ignores_accents(): void
    {
        $this->live('Café Manager');

        $this->assertSame(['Café Manager'], $this->titles('filter[q]=cafe'));
        $this->assertSame(['Café Manager'], $this->titles('filter[q]='.urlencode('café')));
    }

    public function test_two_letter_words_work(): void
    {
        $this->live('QA Engineer');
        $this->live('Chef');

        $this->assertSame(['QA Engineer'], $this->titles('filter[q]=QA'));
        $this->assertSame(['QA Engineer'], $this->titles('filter[q]=qa'));
    }

    public function test_the_keyword_also_matches_a_tag_and_the_company_name(): void
    {
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['name' => 'Sinar Digital']);
        $this->live('Engineer', tags: ['laravel', 'c++']);
        $this->live('Designer', companyId: $this->otherCompanyId);
        $this->live('Cook');

        $this->assertSame(['Engineer'], $this->titles('filter[q]=laravel'));
        $this->assertSame(['Engineer'], $this->titles('filter[q]=LARAV'));
        $this->assertSame(['Designer'], $this->titles('filter[q]=sinar'));
        $this->assertSame(['Designer'], $this->titles('filter[q]=digital'));
    }

    public function test_every_word_must_match_but_each_through_any_place(): void
    {
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['name' => 'Sinar Digital']);
        $this->live('Engineer', tags: ['laravel']);
        $this->live('Engineer', companyId: $this->otherCompanyId);
        $this->live('Designer', tags: ['laravel']);

        $this->assertSame(['Engineer'], $this->titles('filter[q]=engineer laravel'));
        $this->assertSame(['Engineer'], $this->titles('filter[q]=engineer sinar'));
        $this->assertSame([], $this->titles('filter[q]=designer sinar'));
        $this->assertEqualsCanonicalizing(['Engineer', 'Designer'], $this->titles('filter[q]=laravel'));
    }

    public function test_the_description_is_searched_without_its_markdown_marks(): void
    {
        $this->live('Cook', ['description' => "We use **PHP** and [Laravel](https://example.test) every day,\nwith a special kitchen."]);
        $this->live('Waiter');

        $this->assertSame(['Cook'], $this->titles('filter[q]=laravel'));
        $this->assertSame(['Cook'], $this->titles('filter[q]=php kitchen'));
    }

    public function test_marks_typed_in_the_keyword_cannot_break_the_search(): void
    {
        $this->live('100% Remote');
        $this->live('Backend');

        foreach (['%', '_', '\\', "'", '&', '|', '!', ':*', '(', ')', '<->'] as $text) {
            $this->search('filter[q]='.urlencode($text))->assertOk();
        }

        $this->assertSame(['100% Remote'], $this->titles('filter[q]='.urlencode('100%')));
    }

    public function test_repeated_words_count_once_and_the_keyword_has_limits(): void
    {
        $this->live('Backend Developer');

        $this->assertSame(['Backend Developer'], $this->titles('filter[q]=backend BACKEND Backend'));
        $this->search('filter[q]=a b c d e')->assertOk();
        $this->search('filter[q]=a b c d e f')->assertStatus(422)->assertJsonValidationErrors(['filter.q']);
        $this->search('filter[q]='.str_repeat('a', 101))->assertStatus(422)->assertJsonValidationErrors(['filter.q']);
    }

    // ----- salary -----

    public function test_a_minimum_salary_needs_its_period(): void
    {
        $this->search('filter[salary_min]=5000000')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:salary-period-required');
        $this->search('filter[salary_period_id]=1')->assertOk();
    }

    public function test_the_salary_filter_compares_the_upper_bound_within_the_same_period_for_shown_salaries(): void
    {
        $monthly = $this->referenceId('reference_salary_periods', 'monthly');
        $hourly = $this->referenceId('reference_salary_periods', 'hourly');
        $this->live('Range', ['salary_min' => 8000000, 'salary_max' => 12000000, 'salary_period_id' => $monthly, 'salary_visible' => true]);
        $this->live('Only min', ['salary_min' => 9000000, 'salary_period_id' => $monthly, 'salary_visible' => true]);
        $this->live('Only max', ['salary_max' => 7000000, 'salary_period_id' => $monthly, 'salary_visible' => true]);
        $this->live('Hidden', ['salary_min' => 20000000, 'salary_max' => 30000000, 'salary_period_id' => $monthly, 'salary_visible' => false]);
        $this->live('Hourly', ['salary_min' => 50000, 'salary_max' => 90000000, 'salary_period_id' => $hourly, 'salary_visible' => true]);
        $this->live('No salary');

        $titles = fn (int $amount, int $period): array => $this->titles("filter[salary_min]={$amount}&filter[salary_period_id]={$period}");

        $this->assertEqualsCanonicalizing(['Range', 'Only min', 'Only max'], $titles(1000000, $monthly));
        $this->assertEqualsCanonicalizing(['Range', 'Only min'], $titles(8000000, $monthly));
        $this->assertEqualsCanonicalizing(['Range'], $titles(12000000, $monthly), 'the upper bound itself counts');
        $this->assertSame([], $titles(12000001, $monthly), 'a hidden salary never matches, however high');
        $this->assertSame(['Hourly'], $titles(60000, $hourly));
    }

    // ----- order and pages -----

    public function test_the_default_order_is_the_newest_publication_first_and_ties_follow_the_id(): void
    {
        $this->live('Old', ['published_at' => '2026-09-01 08:00:00']);
        $this->live('New', ['published_at' => '2026-10-04 08:00:00']);
        $first = $this->live('Tie one', ['published_at' => '2026-10-02 08:00:00']);
        $this->live('Tie two', ['published_at' => '2026-10-02 08:00:00']);

        $this->assertSame(['New', 'Tie two', 'Tie one', 'Old'], $this->titles());
        $this->assertSame(['New', 'Tie two', 'Tie one', 'Old'], $this->titles('sort=-published_at'));
        $this->assertSame(['Old', 'Tie one', 'Tie two', 'New'], $this->titles('sort=published_at'));
        $this->assertGreaterThan(0, $first);
    }

    public function test_the_search_can_be_ordered_by_deadline(): void
    {
        $this->live('Soon', ['deadline' => '2026-10-20']);
        $this->live('Later', ['deadline' => '2026-12-01']);
        $this->live('Middle', ['deadline' => '2026-11-10']);

        $this->assertSame(['Soon', 'Middle', 'Later'], $this->titles('sort=deadline'));
        $this->assertSame(['Later', 'Middle', 'Soon'], $this->titles('sort=-deadline'));
    }

    public function test_the_search_is_paginated_with_a_total(): void
    {
        foreach (range(1, 5) as $number) {
            $this->live("Job {$number}", ['published_at' => "2026-10-0{$number} 08:00:00"]);
        }

        $first = $this->search('per_page=2')->assertOk();
        $this->assertSame(['Job 5', 'Job 4'], array_column($first->json('data'), 'title'));
        $this->assertSame([5, 1, 2], [$first->json('meta.total'), $first->json('meta.current_page'), $first->json('meta.per_page')]);

        $this->assertSame(['Job 1'], $this->titles('per_page=2&page=3'));
        $this->assertSame([], $this->titles('per_page=2&page=4'));
    }

    public function test_a_very_deep_position_is_refused(): void
    {
        $this->live('Job');

        $this->search('page=200&per_page=50')->assertOk();
        $this->search('page=201&per_page=50')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:page-out-of-range');
        $this->search('page=10001&per_page=1')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:page-out-of-range');
    }

    // ----- validation -----

    public function test_unknown_filters_and_bad_values_are_refused(): void
    {
        foreach ([
            'filter[owner]=1', 'filter[status]=active', 'sort=salary', 'per_page=51', 'per_page=0', 'page=0', 'page=abc',
            'filter[category_id]=a', 'filter[category_id]=0', 'filter[category_id]=1,,2', 'filter[category_id]=1,x', 'filter[category_id]=-1',
            'filter[category_id]='.implode(',', range(1, 21)), 'filter[work_mode]=office', 'filter[work_mode]=onsite,office',
            'filter[salary_min]=-5&filter[salary_period_id]=1', 'filter[salary_min]=abc&filter[salary_period_id]=1', 'filter[salary_period_id]=0',
            'filter[category_id][]=1',
        ] as $query) {
            $this->search($query)->assertStatus(422);
        }
    }

    public function test_twenty_values_are_allowed_and_blank_filters_are_ignored(): void
    {
        $this->live('Job');

        $this->search('filter[category_id]='.implode(',', range(1, 20)))->assertOk();
        $this->assertSame(['Job'], $this->titles('filter[q]=&filter[category_id]=&filter[work_mode]='));
    }

    // ----- limits, cache, callers -----

    public function test_a_client_address_may_search_sixty_times_a_minute(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->search()->assertOk();
        }

        $response = $this->search()->assertStatus(429)->assertJsonPath('type', 'urn:onesis:error:too-many-requests');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
    }

    public function test_another_client_address_has_its_own_allowance(): void
    {
        for ($i = 0; $i < 61; $i++) {
            $this->search();
        }

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])->getJson('/api/v1/jobs')->assertOk();
    }

    public function test_the_detail_page_is_not_limited(): void
    {
        $id = $this->live('Job');
        // Only the search limiter is under test here, not the baseline limit of the whole API.
        config(['security.rate_limit.guest' => 1000]);

        for ($i = 0; $i < 70; $i++) {
            $this->search();
        }

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/jobs/{$id}")->assertOk();
    }

    public function test_the_answer_can_be_cached_for_a_minute_and_revalidated(): void
    {
        $this->live('Job');

        $first = $this->search()->assertOk();
        $etag = $first->headers->get('ETag');

        $this->assertStringContainsString('public', (string) $first->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=60', (string) $first->headers->get('Cache-Control'));
        $this->assertNotNull($etag);
        $this->search('', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_a_signed_in_caller_gets_the_same_answer(): void
    {
        $this->live('Job');

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->recruiterToken)->getJson('/api/v1/jobs')->assertOk()->assertJsonPath('data.0.title', 'Job');
    }

    public function test_a_page_of_results_needs_a_handful_of_queries_not_one_per_row(): void
    {
        $chain = $this->locationChain();
        $type = $this->referenceId('reference_job_types', 'full-time');
        $level = $this->referenceId('reference_job_levels', 'senior');
        $period = $this->referenceId('reference_salary_periods', 'monthly');
        $companies = [$this->companyId, $this->otherCompanyId, $this->companyRowId('pt-third'), $this->companyRowId('pt-fourth')];

        foreach (range(1, 20) as $number) {
            $this->live("Job {$number}", [
                'job_type_id' => $type, 'job_level_id' => $level, 'province_id' => $chain['province_id'], 'city_id' => $chain['city_id'], 'salary_min' => 1000000,
                'salary_period_id' => $period, 'published_at' => "2026-10-01 08:{$number}:00",
            ], companyId: $companies[$number % 4]);
        }

        DB::connection()->enableQueryLog();
        $response = $this->search('per_page=20')->assertOk();
        $queries = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        $this->assertCount(20, $response->json('data'));
        $this->assertLessThanOrEqual(15, $queries, "{$queries} queries for a page of 20 jobs");
    }
}
