<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class CompanyJobListTest extends FeatureTestCase
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

    // ----- access -----

    public function test_every_job_route_needs_a_signed_in_user(): void
    {
        $id = $this->insertJob();

        foreach ([['GET', '/company/jobs'], ['GET', '/company/jobs/status-counts'], ['POST', '/company/jobs'], ['GET', "/company/jobs/{$id}"], ['PATCH', "/company/jobs/{$id}"],
            ['DELETE', "/company/jobs/{$id}"], ['GET', "/company/jobs/{$id}/assignees"], ['PUT', "/company/jobs/{$id}/assignees"]] as [$method, $uri]) {
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
            $this->json($method, '/api/v1'.$uri)->assertStatus(401);
        }
    }

    public function test_a_job_seeker_and_a_suspended_company_are_refused(): void
    {
        $id = $this->insertJob();
        $seeker = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en']);
        $seekerToken = $this->tokenFor((int) $seeker->id);

        foreach ([['GET', '/company/jobs'], ['GET', '/company/jobs/status-counts'], ['POST', '/company/jobs'], ['GET', "/company/jobs/{$id}"], ['PATCH', "/company/jobs/{$id}"],
            ['DELETE', "/company/jobs/{$id}"], ['GET', "/company/jobs/{$id}/assignees"], ['PUT', "/company/jobs/{$id}/assignees"]] as [$method, $uri]) {
            $this->api($method, $uri, ['title' => 'Backend', 'user_ids' => [$this->adminId]], $seekerToken)->assertStatus(403)
                ->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        }

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        foreach ([['GET', '/company/jobs'], ['GET', '/company/jobs/status-counts'], ['POST', '/company/jobs'], ['GET', "/company/jobs/{$id}"], ['PATCH', "/company/jobs/{$id}"],
            ['DELETE', "/company/jobs/{$id}"], ['GET', "/company/jobs/{$id}/assignees"], ['PUT', "/company/jobs/{$id}/assignees"]] as [$method, $uri]) {
            $this->api($method, $uri, ['title' => 'Backend', 'user_ids' => [$this->adminId]])->assertStatus(403)
                ->assertJsonPath('type', 'urn:onesis:error:company-suspended');
        }

        $this->assertNotNull($this->jobRow($id));
    }

    // ----- the list -----

    public function test_a_company_admin_sees_every_job_of_the_company_and_nothing_of_another(): void
    {
        $this->insertJob('Mine', 'draft', [$this->adminId]);
        $this->insertJob('Recruiters', 'active', [$this->recruiterId, $this->secondRecruiterId]);
        $this->insertJob('Theirs', 'active', [$this->otherAdminId], $this->otherCompanyId);

        $response = $this->api('GET', '/company/jobs')->assertOk();

        $this->assertEqualsCanonicalizing(['Mine', 'Recruiters'], array_column($response->json('data'), 'title'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_a_recruiter_sees_only_the_jobs_assigned_to_them(): void
    {
        $this->insertJob('Admin only', 'draft', [$this->adminId]);
        $this->insertJob('Mine', 'draft', [$this->recruiterId]);
        $this->insertJob('Shared', 'active', [$this->adminId, $this->recruiterId]);
        $this->insertJob('Sari only', 'draft', [$this->secondRecruiterId]);

        $response = $this->api('GET', '/company/jobs', token: $this->recruiterToken)->assertOk();

        $this->assertEqualsCanonicalizing(['Mine', 'Shared'], array_column($response->json('data'), 'title'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_a_row_shows_the_summary_fields_and_the_number_of_assignees(): void
    {
        $id = $this->insertJob('Backend Developer', 'active', [$this->adminId, $this->recruiterId], attributes: [
            'deadline' => '2026-12-31', 'vacancies' => 3, 'published_at' => '2026-10-02 08:00:00', 'created_at' => '2026-10-01 07:00:00', 'updated_at' => '2026-10-03 06:00:00',
        ]);

        $row = $this->api('GET', '/company/jobs')->assertOk()->json('data.0');

        $this->assertSame([
            'id' => $id, 'title' => 'Backend Developer', 'summary' => null, 'status' => 'active', 'deadline' => '2026-12-31', 'vacancies' => 3, 'hires_count' => 0, 'filled' => false, 'assignees_count' => 2,
            'applications_count' => 0, 'work_mode' => null, 'location' => ['city' => null, 'province' => null], 'created_by' => ['id' => $this->adminId, 'name' => 'Andi Admin'],
            'published_at' => '2026-10-02T08:00:00+00:00', 'created_at' => '2026-10-01T07:00:00+00:00', 'updated_at' => '2026-10-03T06:00:00+00:00',
        ], $row);
    }

    public function test_the_list_filters_by_status_title_and_assignment(): void
    {
        $this->insertJob('Backend Developer', 'active', [$this->adminId]);
        $this->insertJob('Frontend Developer', 'draft', [$this->recruiterId]);
        $this->insertJob('Data Analyst', 'draft', [$this->recruiterId, $this->adminId]);

        $titles = fn (string $query): array => array_column($this->api('GET', '/company/jobs?'.$query)->assertOk()->json('data'), 'title');

        $this->assertEqualsCanonicalizing(['Frontend Developer', 'Data Analyst'], $titles('filter[status]=draft'));
        $this->assertEqualsCanonicalizing(['Backend Developer', 'Frontend Developer'], $titles('filter[q]=DEVELOPER'));
        $this->assertSame(['Frontend Developer'], $titles('filter[status]=draft&filter[q]=front'));
        $this->assertEqualsCanonicalizing(['Backend Developer', 'Data Analyst'], $titles('filter[assigned_to_me]=true'));
        $this->assertSame([], $titles('filter[status]=expired'));
    }

    public function test_the_search_treats_wildcards_literally(): void
    {
        $this->insertJob('100% Remote', 'draft');
        $this->insertJob('Backend', 'draft');

        $this->assertSame(['100% Remote'], array_column($this->api('GET', '/company/jobs?filter[q]=%25')->json('data'), 'title'));
        $this->assertSame([], array_column($this->api('GET', '/company/jobs?filter[q]=_')->json('data'), 'title'));
    }

    public function test_the_list_sorts_and_the_default_is_newest_update_first(): void
    {
        $this->insertJob('Beta', 'draft', attributes: ['updated_at' => '2026-10-02 08:00:00', 'created_at' => '2026-09-01 08:00:00', 'deadline' => '2026-12-01']);
        $this->insertJob('Alpha', 'draft', attributes: ['updated_at' => '2026-10-04 08:00:00', 'created_at' => '2026-09-03 08:00:00', 'deadline' => '2026-11-01']);
        $this->insertJob('Gamma', 'draft', attributes: ['updated_at' => '2026-10-03 08:00:00', 'created_at' => '2026-09-02 08:00:00', 'deadline' => '2026-10-15']);

        $titles = fn (string $sort): array => array_column($this->api('GET', '/company/jobs'.$sort)->assertOk()->json('data'), 'title');

        $this->assertSame(['Alpha', 'Gamma', 'Beta'], $titles(''));
        $this->assertSame(['Beta', 'Gamma', 'Alpha'], $titles('?sort=updated_at'));
        $this->assertSame(['Alpha', 'Gamma', 'Beta'], $titles('?sort=-created_at'));
        $this->assertSame(['Beta', 'Gamma', 'Alpha'], $titles('?sort=created_at'));
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $titles('?sort=title'));
        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $titles('?sort=-title'));
        $this->assertSame(['Gamma', 'Alpha', 'Beta'], $titles('?sort=deadline'));
        $this->assertSame(['Beta', 'Alpha', 'Gamma'], $titles('?sort=-deadline'));
    }

    public function test_the_list_is_paginated_with_a_total(): void
    {
        foreach (range(1, 5) as $number) {
            $this->insertJob("Job {$number}", 'draft', attributes: ['updated_at' => "2026-10-0{$number} 08:00:00"]);
        }

        $first = $this->api('GET', '/company/jobs?per_page=2')->assertOk();
        $this->assertSame(['Job 5', 'Job 4'], array_column($first->json('data'), 'title'));
        $this->assertSame(5, $first->json('meta.total'));

        $last = $this->api('GET', '/company/jobs?per_page=2&page=3')->assertOk();
        $this->assertSame(['Job 1'], array_column($last->json('data'), 'title'));
    }

    public function test_unknown_filters_sorts_and_values_are_refused(): void
    {
        $this->api('GET', '/company/jobs?filter[owner]=1')->assertStatus(422);
        $this->api('GET', '/company/jobs?filter[status]=published')->assertStatus(422);
        $this->api('GET', '/company/jobs?sort=salary')->assertStatus(422);
        $this->api('GET', '/company/jobs?per_page=101')->assertStatus(422);
        $this->api('GET', '/company/jobs?filter[assigned_to_me]=maybe')->assertStatus(422);
    }

    // ----- the detail -----

    // ----- the status counts -----

    public function test_the_status_counts_cover_the_own_company_with_a_zero_for_every_empty_status(): void
    {
        $empty = ['all' => 0, 'draft' => 0, 'pending_review' => 0, 'active' => 0, 'closed' => 0, 'expired' => 0, 'rejected' => 0, 'taken_down' => 0];
        $this->assertSame(['data' => $empty], $this->api('GET', '/company/jobs/status-counts')->assertOk()->json());

        $this->insertJob('One', 'active', [$this->adminId]);
        $this->insertJob('Two', 'active', [$this->recruiterId]);
        $this->insertJob('Three', 'draft', [$this->adminId]);
        $this->insertJob('Theirs', 'active', [$this->otherAdminId], $this->otherCompanyId);

        $this->assertSame(['data' => [...$empty, 'all' => 3, 'draft' => 1, 'active' => 2]], $this->api('GET', '/company/jobs/status-counts')->assertOk()->json());
    }

    public function test_the_status_counts_follow_the_visibility_and_the_filters_of_the_list(): void
    {
        $this->insertJob('Backend Developer', 'active', [$this->adminId, $this->recruiterId], attributes: ['deadline' => '2026-11-01']);
        $this->insertJob('Backend Tester', 'draft', [$this->adminId], attributes: ['deadline' => '2026-12-01']);
        $this->insertJob('Designer', 'active', [$this->secondRecruiterId]);

        $recruiter = $this->api('GET', '/company/jobs/status-counts', token: $this->recruiterToken)->assertOk()->json('data');
        $this->assertSame([1, 1, 0], [$recruiter['all'], $recruiter['active'], $recruiter['draft']]);

        $byTitle = $this->api('GET', '/company/jobs/status-counts?filter[q]=backend')->assertOk()->json('data');
        $this->assertSame([2, 1, 1], [$byTitle['all'], $byTitle['active'], $byTitle['draft']]);

        $mine = $this->api('GET', '/company/jobs/status-counts?filter[assigned_to_me]=true')->assertOk()->json('data');
        $this->assertSame([2, 1, 1], [$mine['all'], $mine['active'], $mine['draft']]);

        $byDeadline = $this->api('GET', '/company/jobs/status-counts?filter[deadline_to]=2026-11-15')->assertOk()->json('data');
        $this->assertSame([1, 1, 0], [$byDeadline['all'], $byDeadline['active'], $byDeadline['draft']]);

        $withoutApplications = $this->api('GET', '/company/jobs/status-counts?filter[applications]=0')->assertOk()->json('data');
        $this->assertSame(3, $withoutApplications['all']);
    }

    public function test_the_status_counts_refuse_a_status_filter_and_unknown_filters(): void
    {
        $this->api('GET', '/company/jobs/status-counts?filter[status]=active')->assertStatus(422);
        $this->api('GET', '/company/jobs/status-counts?filter[nope]=1')->assertStatus(422);
        $this->api('GET', '/company/jobs/status-counts?filter[applications]=1')->assertStatus(422);
    }

    public function test_the_detail_shows_the_names_in_the_request_language_and_the_assignees(): void
    {
        $body = $this->fullJobBody();
        DB::table('reference_skills')->where('id', $body['skill_ids'][0])->update(['name' => json_encode(['id' => 'PHP (id)', 'en' => 'PHP (en)'])]);
        $id = $this->api('POST', '/company/jobs', $body)->json('data.id');
        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->adminId, $this->recruiterId]])->assertOk();

        $indonesian = $this->api('GET', "/company/jobs/{$id}", headers: ['Accept-Language' => 'id'])->assertOk()->json('data');
        $english = $this->api('GET', "/company/jobs/{$id}", headers: ['Accept-Language' => 'en'])->assertOk()->json('data');

        $this->assertSame('PHP (id)', $indonesian['skills'][0]['name']);
        $this->assertSame('PHP (en)', $english['skills'][0]['name']);
        $this->assertSame(
            [['user_id' => $this->adminId, 'name' => 'Andi Admin', 'role' => 'company_admin', 'job_role' => 'recruiter'], ['user_id' => $this->recruiterId, 'name' => 'Rina Rekruter', 'role' => 'member', 'job_role' => 'recruiter']],
            $english['assignees'],
        );
    }

    public function test_the_reason_shows_only_for_a_rejected_or_taken_down_job(): void
    {
        $rejected = $this->insertJob('Rejected', 'rejected', attributes: ['status_reason' => 'Unclear text.']);
        $takenDown = $this->insertJob('Down', 'taken_down', attributes: ['status_reason' => 'Misleading.']);
        $active = $this->insertJob('Live', 'active', attributes: ['status_reason' => 'Old reason.']);

        $this->assertSame('Unclear text.', $this->api('GET', "/company/jobs/{$rejected}")->json('data.status_reason'));
        $this->assertSame('Misleading.', $this->api('GET', "/company/jobs/{$takenDown}")->json('data.status_reason'));
        $this->assertArrayNotHasKey('status_reason', $this->api('GET', "/company/jobs/{$active}")->json('data'));
    }
}
