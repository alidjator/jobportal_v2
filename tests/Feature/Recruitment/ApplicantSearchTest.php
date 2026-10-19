<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/**
 * `docs/design/phase-8-applicant-search.md` sections 2 to 4: the keyword search, the filters and sorts of the applicant lists, and the list across jobs. The
 * facets of each application are set directly, so every filter is tested against known values.
 */
final class ApplicantSearchTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $jobA;

    private int $jobB;

    /** Siti on job A (the first), Budi on job A, Siti on job B (the latest). */
    private int $first;

    private int $second;

    private int $third;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->verifyTheCompany();
        $this->jobA = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->jobB = $this->insertJob('Data Analyst', 'active', [$this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->first = (int) $this->apply($this->jobA)->assertCreated()->json('data.id');

        Carbon::setTestNow('2026-10-05 10:00:00');
        $budi = $this->seeker('budi.s@example.test', name: 'Budi Santoso');
        $budiToken = $this->tokenFor($budi);
        $budiResume = $this->completeProfile($budiToken);
        $this->second = (int) $this->api('POST', "/jobs/{$this->jobA}/applications", ['resume_id' => $budiResume], $budiToken)->assertCreated()->json('data.id');

        Carbon::setTestNow('2026-10-06 09:00:00');
        $this->third = (int) $this->apply($this->jobB)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** The company's applicants across jobs. @param  array<string, mixed>  $query */
    private function all(array $query = [], ?string $token = null): TestResponse
    {
        return $this->api('GET', '/company/applications?'.http_build_query($query), token: $token ?? $this->adminToken);
    }

    /** One job's list. @param  array<string, mixed>  $query */
    private function ofJob(array $query = [], ?int $job = null): TestResponse
    {
        return $this->api('GET', '/company/jobs/'.($job ?? $this->jobA).'/applications?'.http_build_query($query), token: $this->adminToken);
    }

    /** @return list<int> */
    private function ids(TestResponse $response): array
    {
        return array_map('intval', array_column($response->assertOk()->json('data'), 'id'));
    }

    /** @param  array<string, mixed>  $facets */
    private function facets(int $applicationId, array $facets): void
    {
        DB::table('recruitment_application_facets')->where('application_id', $applicationId)->update($facets);
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function moveTo(int $applicationId, string $type): void
    {
        DB::table('recruitment_applications')->where('id', $applicationId)->update(['stage_id' => $this->stage($type), 'stage_type' => $type]);
    }

    // ----- the list across jobs -----

    public function test_the_list_across_jobs_shows_the_active_applications_with_their_job_newest_first(): void
    {
        $response = $this->all()->assertOk()->assertJsonPath('meta.total', 3);

        $this->assertSame([$this->third, $this->second, $this->first], array_map('intval', array_column($response->json('data'), 'id')));
        $this->assertSame(['id' => $this->jobB, 'title' => 'Data Analyst'], $response->json('data.0.job'));
        $this->assertSame(['id' => $this->jobA, 'title' => 'Backend Developer'], $response->json('data.2.job'));
        $this->assertArrayHasKey('match_score', $response->json('data.0'));
        $this->assertSame(3, array_sum(array_column($response->json('stages'), 'count')));
    }

    public function test_hired_rejected_and_withdrawn_applications_show_only_when_a_stage_filter_asks_for_them(): void
    {
        $this->moveTo($this->first, 'rejected');
        $this->moveTo($this->second, 'hired');

        $this->assertSame([$this->third], $this->ids($this->all()));
        $this->assertSame([$this->first], $this->ids($this->all(['filter' => ['stage_type' => 'rejected']])));
        $this->assertSame([$this->second], $this->ids($this->all(['filter' => ['stage_id' => $this->stage('hired')]])));

        // The columns count every stage, so the rejected and hired columns show their numbers on the default list too.
        $counts = array_column($this->all()->json('stages'), 'count', 'type');
        $this->assertSame(1, $counts['applied']);
        $this->assertSame(1, $counts['rejected']);
        $this->assertSame(1, $counts['hired']);
    }

    public function test_the_list_across_jobs_is_limited_to_the_jobs_the_member_may_see(): void
    {
        $this->assertSame([$this->second, $this->first], $this->ids($this->all(token: $this->recruiterToken)));
        $this->assertSame([$this->third], $this->ids($this->all(token: $this->secondRecruiterToken)));
        $this->assertSame([], $this->ids($this->all(token: $this->otherAdminToken)));

        // A job the member may not see is left out of `filter[job_id]` as if it did not exist.
        $this->assertSame([$this->third], $this->ids($this->all(['filter' => ['job_id' => (string) $this->jobB]])));
        $this->assertSame([], $this->ids($this->all(['filter' => ['job_id' => (string) $this->jobB], 'page' => 1], $this->recruiterToken)));
        $this->assertSame([$this->third, $this->second, $this->first], $this->ids($this->all(['filter' => ['job_id' => $this->jobA.','.$this->jobB]])));
        $this->assertSame([$this->second, $this->first], $this->ids($this->all(['filter' => ['job_id' => $this->jobA.','.$this->jobB]], $this->recruiterToken)));
    }

    public function test_a_member_with_view_all_sees_every_job_and_one_without_the_permission_is_refused(): void
    {
        $viewAll = $this->memberWith(['applications.view', 'jobs.view_all'], 'all@example.test');
        $this->assertCount(3, $this->ids($this->all(token: $viewAll['token'])));

        $none = $this->memberWith([], 'none@example.test');
        $this->all(token: $none['token'])->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/company/applications')->assertStatus(401);
    }

    public function test_a_hiring_manager_bound_to_a_job_sees_only_the_applications_that_carry_the_marker(): void
    {
        DB::table('job_assignments')->where('job_id', $this->jobA)->where('user_id', $this->recruiterId)->update(['role' => 'hiring_manager']);
        DB::table('recruitment_applications')->update(['hiring_manager_visible_at' => null]);
        DB::table('recruitment_applications')->where('id', $this->second)->update(['hiring_manager_visible_at' => now()]);

        $this->assertSame([$this->second], $this->ids($this->all(token: $this->recruiterToken)));
        $this->assertSame(1, array_sum(array_column($this->all(token: $this->recruiterToken)->json('stages'), 'count')));
    }

    // ----- search and filters -----

    public function test_the_keyword_search_reads_the_name_and_the_words_of_the_profile_ignoring_case_and_accents(): void
    {
        $this->facets($this->second, ['search_text' => 'Pengembang Café Bogor Institut Pertanian']);

        $this->assertSame([$this->second], $this->ids($this->all(['filter' => ['q' => 'budi'], 'sort' => '-applied_at'])));
        $this->assertSame([$this->second], $this->ids($this->all(['filter' => ['q' => 'CAFE'], 'sort' => '-applied_at'])));
        $this->assertSame([$this->second], $this->ids($this->all(['filter' => ['q' => 'pertanian']])));
        $this->assertSame([$this->third, $this->first], $this->ids($this->all(['filter' => ['q' => 'PT Lama']])));
        $this->assertSame([$this->second], $this->ids($this->ofJob(['filter' => ['q' => 'bogor']])));
        $this->assertSame([], $this->ids($this->all(['filter' => ['q' => '%%']])));

        $this->all(['filter' => ['q' => 'a']])->assertStatus(422);
        $this->ofJob(['filter' => ['q' => 'a']])->assertStatus(422);
    }

    public function test_the_profile_filters_read_the_facets_and_combine(): void
    {
        $chain = $this->locationChain();
        $otherProvince = (int) DB::table('reference_provinces')->insertGetId(['bps_code' => '31', 'name' => 'DKI JAKARTA']);
        $otherCity = (int) DB::table('reference_cities')->insertGetId(['province_id' => $otherProvince, 'bps_code' => '3171', 'name' => 'JAKARTA SELATAN', 'type' => 'city']);
        $bachelor = $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]);
        $master = $this->referenceId('reference_education_levels', 'master', ['rank' => 6]);
        $high = $this->referenceId('reference_education_levels', 'high-school', ['rank' => 3]);
        $php = $this->skillId('php');
        $sql = $this->skillId('sql');

        $this->facets($this->first, ['education_rank' => 5, 'experience_months' => 28, 'skill_ids' => '{'.$php.'}', 'expected_salary_monthly' => 9_000_000]);
        $this->facets($this->second, ['province_id' => $otherProvince, 'city_id' => $otherCity, 'education_rank' => 6, 'experience_months' => 80, 'skill_ids' => '{'.$php.','.$sql.'}', 'expected_salary_monthly' => 15_000_000]);
        $this->facets($this->third, ['education_rank' => null, 'experience_months' => 5, 'skill_ids' => '{}', 'expected_salary_monthly' => null]);

        $ids = fn (array $filter): array => $this->ids($this->all(['filter' => $filter, 'sort' => 'applied_at']));

        $this->assertSame([$this->first, $this->third], $ids(['province_id' => $chain['province_id']]));
        $this->assertSame([$this->second], $ids(['city_id' => $otherCity]));
        // A higher rank matches too; an application without an education never does.
        $this->assertSame([$this->first, $this->second], $ids(['education_min' => $bachelor]));
        $this->assertSame([$this->second], $ids(['education_min' => $master]));
        $this->assertSame([$this->first, $this->second], $ids(['education_min' => $high]));
        $this->assertSame([], $ids(['education_min' => 999_999]));
        // Whole years: 2 to 6 years is 24 to 83 months.
        $this->assertSame([$this->first, $this->second], $ids(['experience_min' => 2]));
        $this->assertSame([$this->first, $this->second], $ids(['experience_min' => 2, 'experience_max' => 6]));
        $this->assertSame([$this->first], $ids(['experience_min' => 2, 'experience_max' => 2]));
        $this->assertSame([$this->third], $ids(['experience_max' => 0]));
        $this->assertSame([$this->second], $ids(['experience_min' => 6]));
        // Every skill asked for must be present.
        $this->assertSame([$this->first, $this->second], $ids(['skill_ids' => (string) $php]));
        $this->assertSame([$this->second], $ids(['skill_ids' => $php.','.$sql]));
        // An applicant without an expected salary does not match.
        $this->assertSame([$this->first], $ids(['salary_max' => 10_000_000]));
        $this->assertSame([$this->first, $this->second], $ids(['salary_max' => 15_000_000]));
        $this->assertSame([$this->second], $ids(['province_id' => $otherProvince, 'education_min' => $bachelor, 'skill_ids' => (string) $sql]));
    }

    public function test_the_score_and_rating_filters_and_sorts_put_unscored_and_unrated_applications_last(): void
    {
        DB::table('recruitment_applications')->where('id', $this->first)->update(['match_score' => 70]);
        DB::table('recruitment_applications')->where('id', $this->second)->update(['match_score' => 90]);
        DB::table('recruitment_applications')->where('id', $this->third)->update(['match_score' => null]);

        $this->api('PUT', "/company/applications/{$this->first}/rating", ['stars' => 5], $this->adminToken)->assertSuccessful();
        $this->api('PUT', "/company/applications/{$this->first}/rating", ['stars' => 3], $this->recruiterToken)->assertSuccessful();
        $this->api('PUT', "/company/applications/{$this->second}/rating", ['stars' => 2], $this->adminToken)->assertSuccessful();

        $this->assertSame([$this->second], $this->ids($this->all(['filter' => ['match_min' => 80]])));
        $this->assertSame([$this->first, $this->second], $this->ids($this->all(['filter' => ['match_min' => 70], 'sort' => 'applied_at'])));
        $this->assertSame([$this->first], $this->ids($this->all(['filter' => ['rating_min' => 4]])));
        $this->assertSame([$this->first, $this->second], $this->ids($this->all(['filter' => ['rating_min' => 2], 'sort' => 'applied_at'])));

        $this->assertSame([$this->second, $this->first, $this->third], $this->ids($this->all(['sort' => '-match_score'])));
        $this->assertSame([$this->first, $this->second, $this->third], $this->ids($this->all(['sort' => 'match_score'])));
        $this->assertSame([$this->first, $this->second, $this->third], $this->ids($this->all(['sort' => '-rating'])));
        $this->assertSame([$this->second, $this->first, $this->third], $this->ids($this->all(['sort' => 'rating'])));

        $rows = collect($this->all(['sort' => '-match_score'])->json('data'))->keyBy('id');
        $this->assertSame(90, $rows[$this->second]['match_score']);
        $this->assertNull($rows[$this->third]['match_score']);
        $this->assertEquals(4.0, $rows[$this->first]['rating']['average']);

        // The same filters and sorts work on one job's list.
        $this->assertSame([$this->second, $this->first], $this->ids($this->ofJob(['sort' => '-match_score'])));
        $this->assertSame([$this->first], $this->ids($this->ofJob(['filter' => ['rating_min' => 3]])));
    }

    public function test_the_origin_and_time_filters_use_jakarta_days(): void
    {
        $this->facets($this->first, ['from_invitation' => true]);
        $this->facets($this->third, ['returning' => true]);

        $this->assertSame([$this->first], $this->ids($this->all(['filter' => ['invited' => 'true']])));
        $this->assertSame([$this->third, $this->second], $this->ids($this->all(['filter' => ['invited' => 'false']])));
        $this->assertSame([$this->third], $this->ids($this->all(['filter' => ['returning' => 'true']])));
        $this->assertSame([$this->second, $this->first], $this->ids($this->all(['filter' => ['returning' => 'false']])));

        // The first two applied on 5 October 2026 UTC (16:00 and 17:00 Jakarta), the last on 6 October at 16:00 Jakarta.
        $this->assertSame([$this->third], $this->ids($this->all(['filter' => ['applied_from' => '2026-10-06']])));
        $this->assertSame([$this->second, $this->first], $this->ids($this->all(['filter' => ['applied_to' => '2026-10-05']])));
        $this->assertSame([$this->third, $this->second, $this->first], $this->ids($this->all(['filter' => ['applied_from' => '2026-10-05', 'applied_to' => '2026-10-06']])));
        $this->assertSame([], $this->ids($this->all(['filter' => ['applied_from' => '2026-10-07']])));

        // An application applied late in the Jakarta evening belongs to the next Jakarta day.
        DB::table('recruitment_applications')->where('id', $this->first)->update(['applied_at' => '2026-10-05 17:30:00']);
        $this->assertContains($this->first, $this->ids($this->all(['filter' => ['applied_from' => '2026-10-06', 'applied_to' => '2026-10-06']])));
        $this->assertNotContains($this->first, $this->ids($this->all(['filter' => ['applied_to' => '2026-10-05']])));
    }

    public function test_the_process_filter_matches_a_pending_interview_or_a_pending_offer(): void
    {
        $this->api('POST', "/company/applications/{$this->first}/move", ['stage_id' => $this->stage('interview'), 'interview' => $this->interviewSchedule()], $this->recruiterToken)->assertOk();
        $this->api('POST', "/company/applications/{$this->second}/move", ['stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails()], $this->recruiterToken)->assertOk();

        $this->assertSame([$this->first], $this->ids($this->all(['filter' => ['state' => 'interview_scheduled']])));
        $this->assertSame([$this->second], $this->ids($this->all(['filter' => ['state' => 'offer_pending']])));
        $this->assertSame([$this->second, $this->first], $this->ids($this->all(['filter' => ['state' => 'interview_scheduled,offer_pending']])));
        $this->assertSame([$this->first], $this->ids($this->ofJob(['filter' => ['state' => 'interview_scheduled']])));
        // Nothing waits for a test, so that state matches nothing.
        $this->assertSame([], $this->ids($this->all(['filter' => ['state' => 'test_pending']])));
    }

    // ----- counts and validation -----

    public function test_the_counts_per_stage_follow_the_filters_on_both_lists(): void
    {
        $this->facets($this->first, ['experience_months' => 60]);
        $this->facets($this->second, ['experience_months' => 6]);
        $this->moveTo($this->second, 'screening');

        $stages = fn (TestResponse $response): array => array_column($response->assertOk()->json('stages'), 'count', 'type');

        $this->assertSame(1, $stages($this->all(['filter' => ['experience_min' => 4]]))['applied']);
        $this->assertSame(0, $stages($this->all(['filter' => ['experience_min' => 4]]))['screening']);
        $this->assertSame(1, $stages($this->ofJob(['filter' => ['experience_max' => 1]]))['screening']);
        $this->assertSame(0, $stages($this->ofJob(['filter' => ['experience_max' => 1]]))['applied']);
        // A stage filter narrows the rows but not the columns.
        $this->assertSame(1, $stages($this->ofJob(['filter' => ['stage_type' => 'applied']]))['screening']);
    }

    public function test_a_malformed_filter_or_an_unknown_one_is_refused(): void
    {
        foreach ([
            ['education_min' => 'x'], ['experience_min' => 3, 'experience_max' => 1], ['experience_min' => -1], ['skill_ids' => '1,x'], ['salary_max' => -5],
            ['match_min' => 101], ['rating_min' => 0], ['rating_min' => 6], ['applied_from' => '05-10-2026'], ['applied_from' => '2026-10-06', 'applied_to' => '2026-10-05'],
            ['invited' => 'maybe'], ['returning' => 'maybe'], ['state' => 'done'], ['state' => 'offer_pending,done'], ['job_id' => 'a'], ['age_min' => 20], ['gender' => 'f'],
        ] as $filter) {
            $this->all(['filter' => $filter])->assertStatus(422);
        }

        $this->all(['sort' => 'name'])->assertStatus(422);
        $this->ofJob(['filter' => ['job_id' => (string) $this->jobA]])->assertStatus(422);
        $this->ofJob(['filter' => ['salary_max' => 'x']])->assertStatus(422);
    }

    public function test_an_application_without_facets_matches_only_the_stage_and_date_filters(): void
    {
        DB::table('recruitment_application_facets')->where('application_id', $this->first)->delete();

        $this->assertSame([$this->third, $this->second, $this->first], $this->ids($this->all()));
        $this->assertContains($this->first, $this->ids($this->all(['filter' => ['applied_to' => '2026-10-05', 'stage_type' => 'applied']])));
        $this->assertNotContains($this->first, $this->ids($this->all(['filter' => ['experience_min' => 0]])));
        $this->assertNotContains($this->first, $this->ids($this->all(['filter' => ['q' => 'developer']])));
        $this->assertNotContains($this->first, $this->ids($this->all(['filter' => ['invited' => 'false']])));
    }
}
