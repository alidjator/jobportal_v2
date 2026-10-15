<?php

namespace Tests\Feature\Job;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Job\Application\JobSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class AdminJobTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    private string $token;

    private int $adminAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        [$this->token, $this->adminAccountId] = $this->adminToken(['jobs.manage']);
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: string, 1: int}
     */
    private function adminToken(array $permissions, string $email = 'ops@example.test'): array
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return [$admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken, (int) $admin->id];
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body = [], ?string $token = null, array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/admin/jobs'.$uri, $body, $headers);
    }

    /** @return list<object> */
    private function audit(string $action): array
    {
        return DB::table('platform_audit_logs')->where('action', $action)->orderBy('id')->get()->all();
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function notificationsOf(int $userId): array
    {
        return array_map(
            fn ($row): array => ['type' => (string) $row->type, 'data' => json_decode((string) $row->data, true)],
            DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->orderBy('created_at')->orderBy('id')->get()->all(),
        );
    }

    private function pending(string $title = 'Pending job', array $attributes = []): int
    {
        return $this->insertJob($title, 'pending_review', [$this->adminId, $this->recruiterId], attributes: ['deadline' => '2026-12-31', ...$attributes]);
    }

    // ----- access -----

    public function test_every_admin_job_route_needs_an_admin_with_the_permission(): void
    {
        $id = $this->pending();
        [$weak] = $this->adminToken(['faqs.manage'], 'weak@example.test');

        foreach ([['GET', ''], ['GET', "/{$id}"], ['POST', "/{$id}/approve"], ['POST', "/{$id}/reject"], ['POST', "/{$id}/take-down"], ['POST', "/{$id}/restore"]] as [$method, $suffix]) {
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
            $this->json($method, '/api/v1/admin/jobs'.$suffix)->assertStatus(401);
            $this->admin($method, $suffix, ['reason' => 'Some reason.'], $weak)->assertStatus(403);
            $this->admin($method, $suffix, ['reason' => 'Some reason.'], $this->adminToken)->assertStatus(401);
        }

        $this->assertSame('pending_review', $this->jobRow($id)->status);
    }

    // ----- the list -----

    public function test_an_admin_sees_the_jobs_of_every_company_with_their_company(): void
    {
        $mine = $this->insertJob('Backend Developer', 'active', [$this->adminId, $this->recruiterId], attributes: [
            'deadline' => '2026-12-31', 'vacancies' => 3, 'published_at' => '2026-10-02 08:00:00', 'created_at' => '2026-10-01 07:00:00', 'updated_at' => '2026-10-03 06:00:00',
        ]);
        $theirs = $this->insertJob('Data Analyst', 'draft', [$this->otherAdminId], $this->otherCompanyId);
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['status' => 'suspended']);

        $response = $this->admin('GET', '')->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
        $row = collect($response->json('data'))->firstWhere('id', $mine);
        $this->assertSame([
            'id' => $mine, 'title' => 'Backend Developer', 'status' => 'active', 'company' => ['id' => $this->companyId, 'name' => 'PT-MAJU', 'slug' => 'pt-maju'],
            'deadline' => '2026-12-31', 'vacancies' => 3, 'assignees_count' => 2, 'flagged' => null, 'published_at' => '2026-10-02T08:00:00+00:00',
            'created_at' => '2026-10-01T07:00:00+00:00', 'updated_at' => '2026-10-03T06:00:00+00:00',
        ], $row);
        $this->assertSame($this->otherCompanyId, collect($response->json('data'))->firstWhere('id', $theirs)['company']['id']);
    }

    public function test_the_list_filters_by_status_company_and_a_text_that_matches_the_title_or_the_company_name(): void
    {
        $this->insertJob('Backend Developer', 'active');
        $this->insertJob('Frontend Developer', 'draft');
        $this->insertJob('Designer', 'pending_review', [$this->otherAdminId], $this->otherCompanyId);
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['name' => 'Sinar Digital']);

        $titles = fn (string $query): array => array_column($this->admin('GET', '?'.$query)->assertOk()->json('data'), 'title');

        $this->assertSame(['Designer'], $titles('filter[status]=pending_review'));
        $this->assertEqualsCanonicalizing(['Backend Developer', 'Frontend Developer'], $titles('filter[company_id]='.$this->companyId));
        $this->assertEqualsCanonicalizing(['Backend Developer', 'Frontend Developer'], $titles('filter[q]=DEVELOPER'));
        $this->assertSame(['Designer'], $titles('filter[q]=sinar'));
        $this->assertSame(['Designer'], $titles('filter[q]=DESIGN'));
        $this->assertSame(['Frontend Developer'], $titles('filter[q]=front&filter[status]=draft'));
        $this->assertSame([], $titles('filter[q]=nothing-like-this'));
    }

    public function test_the_search_treats_wildcards_literally_in_titles_and_company_names(): void
    {
        $this->insertJob('100% Remote', 'draft');
        $this->insertJob('Backend', 'draft');
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['name' => 'Under_score']);
        $this->insertJob('Other', 'draft', [$this->otherAdminId], $this->otherCompanyId);

        $titles = fn (string $query): array => array_column($this->admin('GET', '?'.$query)->assertOk()->json('data'), 'title');

        $this->assertSame(['100% Remote'], $titles('filter[q]=%25'));
        $this->assertSame(['Other'], $titles('filter[q]=_'));
    }

    public function test_the_list_sorts_and_the_default_is_the_newest_update_first(): void
    {
        $this->insertJob('Beta', 'draft', attributes: ['updated_at' => '2026-10-02 08:00:00', 'created_at' => '2026-09-01 08:00:00', 'deadline' => '2026-12-01', 'published_at' => '2026-09-10 08:00:00']);
        $this->insertJob('Alpha', 'draft', attributes: ['updated_at' => '2026-10-04 08:00:00', 'created_at' => '2026-09-03 08:00:00', 'deadline' => '2026-11-01', 'published_at' => '2026-09-12 08:00:00']);
        $this->insertJob('Gamma', 'draft', attributes: ['updated_at' => '2026-10-03 08:00:00', 'created_at' => '2026-09-02 08:00:00', 'deadline' => '2026-10-15', 'published_at' => '2026-09-11 08:00:00']);

        $titles = fn (string $sort): array => array_column($this->admin('GET', $sort)->assertOk()->json('data'), 'title');

        $this->assertSame(['Alpha', 'Gamma', 'Beta'], $titles(''));
        $this->assertSame(['Beta', 'Gamma', 'Alpha'], $titles('?sort=updated_at'));
        $this->assertSame(['Alpha', 'Gamma', 'Beta'], $titles('?sort=-created_at'));
        $this->assertSame(['Beta', 'Gamma', 'Alpha'], $titles('?sort=published_at'));
        $this->assertSame(['Alpha', 'Gamma', 'Beta'], $titles('?sort=-published_at'));
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $titles('?sort=title'));
        $this->assertSame(['Gamma', 'Alpha', 'Beta'], $titles('?sort=deadline'));
        $this->assertSame(['Beta', 'Alpha', 'Gamma'], $titles('?sort=-deadline'));
    }

    public function test_the_review_queue_is_oldest_first(): void
    {
        $this->pending('Newer', ['updated_at' => '2026-10-04 08:00:00']);
        $this->pending('Older', ['updated_at' => '2026-10-01 08:00:00']);
        $this->insertJob('Not waiting', 'active', attributes: ['updated_at' => '2026-09-01 08:00:00']);

        $titles = array_column($this->admin('GET', '?filter[status]=pending_review&sort=updated_at')->assertOk()->json('data'), 'title');

        $this->assertSame(['Older', 'Newer'], $titles);
    }

    public function test_the_list_is_paginated_and_refuses_unknown_filters(): void
    {
        foreach (range(1, 5) as $number) {
            $this->insertJob("Job {$number}", 'draft', attributes: ['updated_at' => "2026-10-0{$number} 08:00:00"]);
        }

        $page = $this->admin('GET', '?per_page=2&page=3')->assertOk();
        $this->assertSame(['Job 1'], array_column($page->json('data'), 'title'));
        $this->assertSame(5, $page->json('meta.total'));

        $this->admin('GET', '?filter[owner]=1')->assertStatus(422);
        $this->admin('GET', '?filter[status]=published')->assertStatus(422);
        $this->admin('GET', '?filter[company_id]=abc')->assertStatus(422);
        $this->admin('GET', '?sort=salary')->assertStatus(422);
        $this->admin('GET', '?per_page=101')->assertStatus(422);
    }

    // ----- the detail -----

    public function test_the_detail_shows_the_job_the_company_and_the_decision_details(): void
    {
        $body = $this->fullJobBody();
        $id = (int) $this->api('POST', '/company/jobs', $body)->json('data.id');
        DB::table('job_jobs')->where('id', $id)->update(['status' => 'taken_down', 'status_reason' => 'Misleading.', 'reviewed_at' => '2026-10-04 08:00:00', 'reviewed_by_admin_id' => $this->adminAccountId]);

        $data = $this->admin('GET', "/{$id}")->assertOk()->json('data');

        $this->assertSame(['taken_down', 'Misleading.', '2026-10-04T08:00:00+00:00', $this->adminAccountId], [$data['status'], $data['status_reason'], $data['reviewed_at'], $data['reviewed_by_admin_id']]);
        $this->assertSame(['id' => $this->companyId, 'name' => 'PT-MAJU', 'slug' => 'pt-maju', 'status' => 'active'], $data['company']);
        $this->assertSame($body['title'], $data['title']);
        $this->assertCount(2, $data['skills']);
        $this->assertSame([['user_id' => $this->adminId, 'name' => 'Andi Admin', 'role' => 'company_admin']], $data['assignees']);
    }

    public function test_the_detail_works_for_every_status_and_a_suspended_company_and_an_unknown_job_is_404(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        foreach (['draft', 'pending_review', 'active', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status);

            $this->admin('GET', "/{$id}")->assertOk()->assertJsonPath('data.company.status', 'suspended');
        }

        $this->admin('GET', '/999999')->assertNotFound();
    }

    public function test_a_status_reason_that_does_not_exist_is_left_out(): void
    {
        $id = $this->insertJob('Plain', 'active');

        $this->assertArrayNotHasKey('status_reason', $this->admin('GET', "/{$id}")->json('data'));
    }

    // ----- approving -----

    public function test_approving_puts_the_job_live_records_the_decision_and_tells_the_assignees(): void
    {
        $id = $this->pending();

        $response = $this->admin('POST', "/{$id}/approve")->assertOk();

        $this->assertSame(['active', '2026-10-05T09:00:00+00:00', '2026-10-05T09:00:00+00:00', $this->adminAccountId], [
            $response->json('data.status'), $response->json('data.published_at'), $response->json('data.reviewed_at'), $response->json('data.reviewed_by_admin_id'),
        ]);

        $entry = $this->audit('job.approved')[0];
        $this->assertSame(['admin', $this->adminAccountId, 'job', $id], [$entry->actor_type, (int) $entry->actor_id, $entry->subject_type, (int) $entry->subject_id]);
        $this->assertSame(['from' => 'pending_review', 'to' => 'active'], json_decode((string) $entry->metadata, true));

        foreach ([$this->adminId, $this->recruiterId] as $userId) {
            $this->assertSame([['type' => 'job.approved', 'data' => ['job_id' => $id]]], $this->notificationsOf($userId));
        }
        $this->assertSame([], $this->notificationsOf($this->secondRecruiterId));
    }

    public function test_a_job_that_was_live_before_keeps_its_first_publication_time(): void
    {
        $id = $this->pending(attributes: ['published_at' => '2026-09-01 08:00:00']);

        $this->admin('POST', "/{$id}/approve")->assertOk()->assertJsonPath('data.published_at', '2026-09-01T08:00:00+00:00');
    }

    public function test_only_a_job_waiting_for_review_can_be_approved_or_rejected(): void
    {
        foreach (['draft', 'active', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status, attributes: ['deadline' => '2026-12-31']);

            $this->admin('POST', "/{$id}/approve")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-pending-review');
            $this->admin('POST', "/{$id}/reject", ['reason' => 'Some reason.'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-pending-review');
            $this->assertSame($status, $this->jobRow($id)->status);
        }

        $this->assertSame([], $this->audit('job.approved'));
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_a_job_whose_deadline_has_passed_cannot_be_approved_but_can_be_rejected(): void
    {
        $pending = $this->pending('Late', ['deadline' => '2026-10-04']);

        $this->admin('POST', "/{$pending}/approve")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-deadline-passed');

        $this->assertSame('pending_review', $this->jobRow($pending)->status);
        $this->assertNull($this->jobRow($pending)->reviewed_at);
        $this->assertSame([], $this->audit('job.approved'));
        $this->assertSame(0, DB::table('notifications')->count());

        $this->admin('POST', "/{$pending}/reject", ['reason' => 'The deadline has passed.'])->assertOk();
    }

    public function test_restoring_a_job_whose_deadline_has_passed_makes_it_expired_and_the_company_can_reopen_it(): void
    {
        $down = $this->insertJob('Down late', 'taken_down', [$this->recruiterId], attributes: [
            'deadline' => '2026-10-04', 'status_reason' => 'Misleading.', 'description' => str_repeat('a', 60),
        ]);

        $response = $this->admin('POST', "/{$down}/restore")->assertOk();

        $this->assertSame('expired', $response->json('data.status'));
        $this->assertArrayNotHasKey('status_reason', $response->json('data'));
        $this->assertSame(['from' => 'taken_down', 'to' => 'expired'], json_decode((string) $this->audit('job.restored')[0]->metadata, true));
        $this->assertSame([['type' => 'job.restored', 'data' => ['job_id' => $down]]], $this->notificationsOf($this->recruiterId));

        DB::table('job_jobs')->where('id', $down)->update([
            'category_id' => $this->referenceId('reference_job_categories', 'engineering'), 'role_id' => $this->referenceId('reference_job_roles', 'backend-developer'),
            'job_type_id' => $this->referenceId('reference_job_types', 'full-time'), 'job_level_id' => $this->referenceId('reference_job_levels', 'senior'),
            'work_time_id' => $this->referenceId('reference_work_times', 'day-shift'), 'experience_level_id' => $this->referenceId('reference_experience_levels', 'five-years'),
            'education_level_id' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]), 'vacancies' => 2,
            'work_mode' => 'remote',
        ]);
        DB::table('job_job_skills')->insert(['job_id' => $down, 'skill_id' => $this->skillId('php')]);

        $this->api('POST', "/company/jobs/{$down}/reopen", ['deadline' => '2026-11-30'])->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_restoring_a_job_whose_deadline_is_today_puts_it_live(): void
    {
        $down = $this->insertJob('Down today', 'taken_down', attributes: ['deadline' => '2026-10-05', 'status_reason' => 'Misleading.']);

        $this->admin('POST', "/{$down}/restore")->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_the_deadline_day_itself_can_still_be_approved(): void
    {
        $id = $this->pending('Last day', ['deadline' => '2026-10-05']);

        $this->admin('POST', "/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'active');
    }

    // ----- rejecting -----

    public function test_rejecting_stores_the_reason_audits_it_and_tells_the_assignees_with_the_reason(): void
    {
        $id = $this->pending();

        $response = $this->admin('POST', "/{$id}/reject", ['reason' => '  The description is unclear.  '])->assertOk();

        $this->assertSame(['rejected', 'The description is unclear.', $this->adminAccountId], [
            $response->json('data.status'), $response->json('data.status_reason'), $response->json('data.reviewed_by_admin_id'),
        ]);
        $this->assertNull($this->jobRow($id)->published_at);

        $entry = $this->audit('job.rejected')[0];
        $this->assertSame(['from' => 'pending_review', 'to' => 'rejected', 'reason' => 'The description is unclear.'], json_decode((string) $entry->metadata, true));
        $this->assertSame(
            [['type' => 'job.rejected', 'data' => ['job_id' => $id, 'reason' => 'The description is unclear.']]],
            $this->notificationsOf($this->recruiterId),
        );
    }

    public function test_the_reason_is_required_and_has_a_length_range_for_a_rejection_and_a_take_down(): void
    {
        $pending = $this->pending();
        $active = $this->insertJob('Live', 'active');

        foreach ([[$pending, 'reject'], [$active, 'take-down']] as [$id, $action]) {
            $this->admin('POST', "/{$id}/{$action}", [])->assertStatus(422)->assertJsonValidationErrors(['reason']);
            $this->admin('POST', "/{$id}/{$action}", ['reason' => 'abcd'])->assertStatus(422)->assertJsonValidationErrors(['reason']);
            $this->admin('POST', "/{$id}/{$action}", ['reason' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors(['reason']);
            $this->admin('POST', "/{$id}/{$action}", ['reason' => '     '])->assertStatus(422)->assertJsonValidationErrors(['reason']);
        }

        $this->assertSame(['pending_review', 'active'], [$this->jobRow($pending)->status, $this->jobRow($active)->status]);
        $this->assertSame(0, count($this->audit('job.rejected')) + count($this->audit('job.taken_down')));
    }

    public function test_a_rejected_job_can_be_edited_and_published_again_and_its_reason_goes_away(): void
    {
        $id = (int) $this->api('POST', '/company/jobs', $this->fullJobBody())->json('data.id');
        $this->app->make(SettingsStore::class)->setCompanyOverride(JobSettings::REQUIRE_APPROVAL, $this->companyId, true, null);
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'pending_review');
        $this->admin('POST', "/{$id}/reject", ['reason' => 'Please add the salary.'])->assertOk();

        $own = $this->api('GET', "/company/jobs/{$id}")->assertOk();
        $this->assertSame(['rejected', 'Please add the salary.'], [$own->json('data.status'), $own->json('data.status_reason')]);

        $this->api('PATCH', "/company/jobs/{$id}", ['salary_min' => 9000000, 'salary_max' => 12000000])->assertOk()->assertJsonPath('data.status', 'rejected');
        $again = $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $this->assertSame('pending_review', $again->json('data.status'));
        $this->assertArrayNotHasKey('status_reason', $again->json('data'));
        $this->admin('POST', "/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'active');
    }

    // ----- taking down and restoring -----

    public function test_taking_a_job_down_stores_the_reason_and_locks_the_company_out_of_changing_it(): void
    {
        $id = $this->insertJob('Live', 'active', [$this->adminId, $this->recruiterId], attributes: ['deadline' => '2026-12-31']);

        $response = $this->admin('POST', "/{$id}/take-down", ['reason' => 'It misleads applicants.'])->assertOk();

        $this->assertSame(['taken_down', 'It misleads applicants.'], [$response->json('data.status'), $response->json('data.status_reason')]);
        $this->assertSame(['from' => 'active', 'to' => 'taken_down', 'reason' => 'It misleads applicants.'], json_decode((string) $this->audit('job.taken_down')[0]->metadata, true));
        $this->assertSame([['type' => 'job.taken_down', 'data' => ['job_id' => $id, 'reason' => 'It misleads applicants.']]], $this->notificationsOf($this->adminId));

        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Changed'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-editable');
        $this->api('POST', "/company/jobs/{$id}/close")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-active');
        $this->api('POST', "/company/jobs/{$id}/publish")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-publishable');
        $this->api('POST', "/company/jobs/{$id}/reopen", ['deadline' => '2026-11-30'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-reopenable');
        $this->api('GET', "/company/jobs/{$id}")->assertOk()->assertJsonPath('data.status_reason', 'It misleads applicants.');
    }

    public function test_only_a_live_job_can_be_taken_down(): void
    {
        foreach (['draft', 'pending_review', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status);

            $this->admin('POST', "/{$id}/take-down", ['reason' => 'Some reason.'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-active');
            $this->assertSame($status, $this->jobRow($id)->status);
        }
    }

    public function test_restoring_puts_a_taken_down_job_live_and_clears_the_reason(): void
    {
        $id = $this->insertJob('Down', 'taken_down', [$this->recruiterId], attributes: ['deadline' => '2026-12-31', 'status_reason' => 'Misleading.']);

        $response = $this->admin('POST', "/{$id}/restore")->assertOk();

        $this->assertSame('active', $response->json('data.status'));
        $this->assertArrayNotHasKey('status_reason', $response->json('data'));
        $this->assertNull($this->jobRow($id)->status_reason);
        $this->assertSame(['from' => 'taken_down', 'to' => 'active'], json_decode((string) $this->audit('job.restored')[0]->metadata, true));
        $this->assertSame([['type' => 'job.restored', 'data' => ['job_id' => $id]]], $this->notificationsOf($this->recruiterId));
    }

    public function test_only_a_taken_down_job_can_be_restored(): void
    {
        foreach (['draft', 'pending_review', 'active', 'closed', 'expired', 'rejected'] as $status) {
            $id = $this->insertJob("Job {$status}", $status, attributes: ['deadline' => '2026-12-31']);

            $this->admin('POST', "/{$id}/restore")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-taken-down');
            $this->assertSame($status, $this->jobRow($id)->status);
        }
    }

    public function test_the_expiry_never_touches_a_job_that_was_taken_down(): void
    {
        $id = $this->insertJob('Down', 'taken_down', attributes: ['deadline' => '2026-09-01']);

        Artisan::call('job:expire-due');

        $this->assertSame('taken_down', $this->jobRow($id)->status);
    }

    // ----- unknown jobs and the whole path -----

    public function test_an_unknown_job_is_404_for_every_decision_and_leaves_no_trace(): void
    {
        $this->admin('POST', '/999999/approve')->assertNotFound();
        $this->admin('POST', '/999999/reject', ['reason' => 'Some reason.'])->assertNotFound();
        $this->admin('POST', '/999999/take-down', ['reason' => 'Some reason.'])->assertNotFound();
        $this->admin('POST', '/999999/restore')->assertNotFound();

        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'like', 'job.%')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_the_whole_path_from_publishing_to_a_take_down_and_back(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(JobSettings::REQUIRE_APPROVAL, true, null);
        $id = (int) $this->api('POST', '/company/jobs', $this->fullJobBody())->json('data.id');

        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'pending_review');
        $this->admin('POST', "/{$id}/approve")->assertOk();
        $this->assertSame('active', $this->api('GET', "/company/jobs/{$id}")->json('data.status'));

        $this->admin('POST', "/{$id}/take-down", ['reason' => 'Spam content.'])->assertOk();
        $this->admin('POST', "/{$id}/restore")->assertOk();

        $this->assertEqualsCanonicalizing(
            ['job.approved', 'job.taken_down', 'job.restored'],
            array_column($this->notificationsOf($this->adminId), 'type'),
        );
        $this->assertSame(
            ['job.published', 'job.approved', 'job.taken_down', 'job.restored'],
            DB::table('platform_audit_logs')->where('action', 'like', 'job.%')->where('action', '!=', 'job.assignees_changed')->orderBy('id')->pluck('action')->all(),
        );
    }
}
