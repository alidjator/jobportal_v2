<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-job-posting.md` section 4: more filters, two more sorts and more columns on the job list of a company. */
final class CompanyJobListFiltersTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    /** @var array<string, int> */
    private array $jobs = [];

    /** @var array{province_id: int, city_id: int} the place of Gamma */
    private array $jakarta;

    /** @var array<string, int> */
    private array $bandung;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();

        $this->bandung = $this->locationChain();
        $province = (int) DB::table('reference_provinces')->insertGetId(['bps_code' => '31', 'name' => 'DKI JAKARTA']);
        $this->jakarta = ['province_id' => $province, 'city_id' => (int) DB::table('reference_cities')->insertGetId(['province_id' => $province, 'bps_code' => '3171', 'name' => 'JAKARTA SELATAN', 'type' => 'city'])];

        $fullTime = $this->referenceId('reference_job_types', 'full-time');
        $partTime = $this->referenceId('reference_job_types', 'part-time');
        $engineering = $this->referenceId('reference_job_categories', 'engineering');
        $design = $this->referenceId('reference_job_categories', 'design');

        // Alpha is published 23:59:59 on 1 September in Jakarta, Gamma exactly at the start of the next day.
        $this->jobs['Alpha'] = $this->insertJob('Alpha', 'active', [$this->adminId], attributes: [
            'work_mode' => 'onsite', 'province_id' => $this->bandung['province_id'], 'city_id' => $this->bandung['city_id'], 'job_type_id' => $fullTime, 'category_id' => $engineering,
            'published_at' => '2026-09-01 16:59:59', 'deadline' => '2026-11-01', 'vacancies' => 1, 'summary' => 'Alpha builds the portal for everyone.', 'created_by_user_id' => $this->adminId,
        ]);
        $this->jobs['Beta'] = $this->insertJob('Beta', 'draft', [$this->recruiterId], attributes: [
            'work_mode' => 'remote', 'job_type_id' => $partTime, 'category_id' => $design, 'deadline' => '2026-12-01', 'vacancies' => 2, 'created_by_user_id' => $this->recruiterId,
        ]);
        $this->jobs['Gamma'] = $this->insertJob('Gamma', 'active', [$this->recruiterId, $this->secondRecruiterId], attributes: [
            'work_mode' => 'hybrid', 'province_id' => $this->jakarta['province_id'], 'city_id' => $this->jakarta['city_id'], 'job_type_id' => $fullTime, 'category_id' => $design,
            'published_at' => '2026-09-01 17:00:00', 'deadline' => '2027-01-15', 'created_by_user_id' => $this->secondRecruiterId,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /** @return list<string> */
    private function titles(string $query, string $path = '/company/jobs', ?string $token = null): array
    {
        $titles = array_column($this->api('GET', $path.'?'.$query, token: $token)->assertOk()->json('data'), 'title');
        sort($titles);

        return $titles;
    }

    private function application(string $job, string $stageType, string $email): void
    {
        $company = $this->companyId;
        $stage = (int) (DB::table('recruitment_stages')->where('company_id', $company)->where('type', $stageType)->value('id')
            ?? DB::table('recruitment_stages')->insertGetId(['company_id' => $company, 'name' => $stageType, 'type' => $stageType, 'position' => 1, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]));
        $user = $this->userRowId($email);

        DB::table('recruitment_applications')->insert([
            'user_id' => $user, 'job_id' => $this->jobs[$job], 'company_id' => $company, 'stage_id' => $stage, 'stage_type' => $stageType, 'active_key' => "{$user}-{$this->jobs[$job]}",
            'applied_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ----- the rows -----

    public function test_a_row_shows_the_summary_the_place_the_creator_and_the_applications(): void
    {
        $this->application('Alpha', 'applied', 'one@example.test');
        $this->application('Alpha', 'hired', 'two@example.test');

        $rows = array_column($this->api('GET', '/company/jobs')->assertOk()->json('data'), null, 'title');

        $this->assertSame('Alpha builds the portal for everyone.', $rows['Alpha']['summary']);
        $this->assertSame([2, 'onsite', ['city' => 'BANDUNG', 'province' => 'JAWA BARAT'], ['id' => $this->adminId, 'name' => 'Andi Admin']], [
            $rows['Alpha']['applications_count'], $rows['Alpha']['work_mode'], $rows['Alpha']['location'], $rows['Alpha']['created_by'],
        ]);
        $this->assertSame([0, 'remote', ['city' => null, 'province' => null], ['id' => $this->recruiterId, 'name' => 'Rina Rekruter']], [
            $rows['Beta']['applications_count'], $rows['Beta']['work_mode'], $rows['Beta']['location'], $rows['Beta']['created_by'],
        ]);
    }

    public function test_a_withdrawn_application_is_not_counted(): void
    {
        $this->application('Alpha', 'applied', 'one@example.test');
        $this->application('Alpha', 'applied', 'two@example.test');
        DB::table('recruitment_applications')->where('id', DB::table('recruitment_applications')->where('job_id', $this->jobs['Alpha'])->min('id'))->update(['stage_type' => 'withdrawn', 'active_key' => null]);

        $rows = array_column($this->api('GET', '/company/jobs')->assertOk()->json('data'), null, 'title');

        $this->assertSame(1, $rows['Alpha']['applications_count']);
    }

    // ----- the filters -----

    public function test_the_place_filters_take_one_or_several_ids(): void
    {
        $this->assertSame(['Alpha'], $this->titles('filter[province_id]='.$this->bandung['province_id']));
        $this->assertSame(['Alpha', 'Gamma'], $this->titles('filter[province_id]='.$this->bandung['province_id'].','.$this->jakarta['province_id']));
        $this->assertSame(['Gamma'], $this->titles('filter[city_id]='.$this->jakarta['city_id']));
        $this->assertSame([], $this->titles('filter[city_id]='.$this->jakarta['city_id'].'&filter[province_id]='.$this->bandung['province_id']));
    }

    public function test_the_work_mode_the_job_type_and_the_category_filters(): void
    {
        $this->assertSame(['Beta'], $this->titles('filter[work_mode]=remote'));
        $this->assertSame(['Alpha', 'Gamma'], $this->titles('filter[work_mode]=onsite,hybrid'));
        $this->assertSame(['Alpha', 'Gamma'], $this->titles('filter[job_type_id]='.$this->referenceId('reference_job_types', 'full-time')));
        $this->assertSame(['Beta', 'Gamma'], $this->titles('filter[category_id]='.$this->referenceId('reference_job_categories', 'design')));
        $this->assertSame(['Gamma'], $this->titles('filter[category_id]='.$this->referenceId('reference_job_categories', 'design').'&filter[job_type_id]='.$this->referenceId('reference_job_types', 'full-time')));
    }

    public function test_the_publication_dates_are_jakarta_days_and_the_last_day_counts(): void
    {
        $this->assertSame(['Alpha'], $this->titles('filter[published_to]=2026-09-01'));
        $this->assertSame(['Gamma'], $this->titles('filter[published_from]=2026-09-02'));
        $this->assertSame(['Alpha', 'Gamma'], $this->titles('filter[published_from]=2026-09-01&filter[published_to]=2026-09-02'));
        $this->assertSame([], $this->titles('filter[published_from]=2026-09-03'));
    }

    public function test_the_deadline_filters_bound_the_deadline_from_both_sides(): void
    {
        $this->assertSame(['Beta', 'Gamma'], $this->titles('filter[deadline_from]=2026-12-01'));
        $this->assertSame(['Alpha', 'Beta'], $this->titles('filter[deadline_from]=2026-11-01&filter[deadline_to]=2026-12-01'));
    }

    public function test_the_creator_and_assignee_filters(): void
    {
        $this->assertSame(['Beta'], $this->titles('filter[created_by]='.$this->recruiterId));
        $this->assertSame(['Beta', 'Gamma'], $this->titles('filter[assignee]='.$this->recruiterId));
        $this->assertSame(['Gamma'], $this->titles('filter[assignee]='.$this->secondRecruiterId));
    }

    public function test_the_assignee_filter_can_only_narrow_the_jobs_a_recruiter_sees(): void
    {
        $this->assertSame(['Beta', 'Gamma'], $this->titles('', token: $this->recruiterToken));
        $this->assertSame([], $this->titles('filter[assignee]='.$this->adminId, token: $this->recruiterToken));
    }

    public function test_the_filled_filter_compares_the_hires_with_the_vacancies(): void
    {
        $this->application('Alpha', 'hired', 'one@example.test');
        $this->application('Beta', 'hired', 'two@example.test');

        $this->assertSame(['Alpha'], $this->titles('filter[filled]=true'));
        $this->assertSame(['Beta', 'Gamma'], $this->titles('filter[filled]=false'));
    }

    public function test_the_counts_by_status_follow_the_same_filters(): void
    {
        $counts = $this->api('GET', '/company/jobs/status-counts?filter[work_mode]=onsite,remote')->assertOk()->json('data');

        $this->assertSame([2, 1, 1, 0], [$counts['all'], $counts['active'], $counts['draft'], $counts['closed']]);
    }

    public function test_a_malformed_value_is_refused(): void
    {
        foreach (['filter[work_mode]=office', 'filter[province_id]=abc', 'filter[city_id]=1,,2', 'filter[published_from]=01-09-2026', 'filter[published_from]=2026-09-02&filter[published_to]=2026-09-01',
            'filter[created_by]=0', 'filter[filled]=maybe', 'filter[unknown]=1'] as $query) {
            $this->api('GET', '/company/jobs?'.$query)->assertStatus(422);
        }
    }

    // ----- the sorts -----

    public function test_the_list_sorts_by_deadline_and_by_the_number_of_applications(): void
    {
        $this->application('Gamma', 'applied', 'one@example.test');
        $this->application('Gamma', 'applied', 'two@example.test');
        $this->application('Beta', 'applied', 'three@example.test');

        $order = fn (string $sort): array => array_column($this->api('GET', '/company/jobs?sort='.$sort)->assertOk()->json('data'), 'title');

        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $order('-applications_count'));
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $order('applications_count'));
        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $order('-deadline'));
    }
}
