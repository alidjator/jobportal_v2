<?php

namespace Tests\Feature\Job;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Src\Job\Application\JobSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class JobLifecycleTest extends FeatureTestCase
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

    /** A complete draft, created through the API by the company admin. */
    private function completeDraft(string $title = 'Senior Backend Developer', array $override = []): int
    {
        return (int) $this->api('POST', '/company/jobs', [...$this->fullJobBody($title), ...$override])->assertCreated()->json('data.id');
    }

    private function requireApproval(?bool $global = null, ?bool $forCompany = null): void
    {
        $store = $this->app->make(SettingsStore::class);

        if ($global !== null) {
            $store->setGlobal(JobSettings::REQUIRE_APPROVAL, $global, null);
        }

        if ($forCompany !== null) {
            $store->setCompanyOverride(JobSettings::REQUIRE_APPROVAL, $this->companyId, $forCompany, null);
        }
    }

    private function statusOf(int $id): string
    {
        return (string) $this->jobRow($id)->status;
    }

    /** @return list<object> */
    private function audit(string $action): array
    {
        return DB::table('platform_audit_logs')->where('action', $action)->orderBy('id')->get()->all();
    }

    private function auditCount(): int
    {
        return DB::table('platform_audit_logs')->where('action', 'like', 'job.%')->where('action', '!=', 'job.assignees_changed')->count();
    }

    // ----- publishing -----

    public function test_a_complete_draft_goes_live_and_the_first_publication_time_is_kept(): void
    {
        $id = $this->completeDraft();

        $response = $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $this->assertSame(['active', '2026-10-05T09:00:00+00:00'], [$response->json('data.status'), $response->json('data.published_at')]);
        $this->assertSame('2026-10-05 09:00:00', $this->jobRow($id)->published_at);

        $entry = $this->audit('job.published')[0];
        $this->assertSame(['user', $this->adminId, 'job', $id], [$entry->actor_type, (int) $entry->actor_id, $entry->subject_type, (int) $entry->subject_id]);
        $this->assertSame(['from' => 'draft', 'to' => 'active'], json_decode((string) $entry->metadata, true));
    }

    public function test_with_approval_required_a_publication_goes_to_review_and_is_not_live_yet(): void
    {
        $this->requireApproval(forCompany: true);
        $id = $this->completeDraft();

        $response = $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $this->assertSame('pending_review', $response->json('data.status'));
        $this->assertNull($this->jobRow($id)->published_at);
        $this->assertSame(['from' => 'draft', 'to' => 'pending_review'], json_decode((string) $this->audit('job.published')[0]->metadata, true));
    }

    public function test_a_company_override_beats_the_global_approval_setting_in_both_directions(): void
    {
        $this->requireApproval(global: true, forCompany: false);
        $this->api('POST', '/company/jobs/'.$this->completeDraft().'/publish')->assertOk()->assertJsonPath('data.status', 'active');

        $this->requireApproval(global: false, forCompany: true);
        $this->api('POST', '/company/jobs/'.$this->completeDraft('Another').'/publish')->assertOk()->assertJsonPath('data.status', 'pending_review');
    }

    public function test_an_incomplete_draft_is_refused_with_every_missing_field_and_nothing_changes(): void
    {
        $id = (int) $this->api('POST', '/company/jobs', ['title' => 'Only a title'])->json('data.id');

        $response = $this->api('POST', "/company/jobs/{$id}/publish")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:job-incomplete');

        $this->assertSame(
            ['description', 'category_id', 'role_id', 'job_type_id', 'job_level_id', 'work_time_id', 'experience_level_id', 'education_level_id', 'vacancies',
                'skill_ids', 'province_id', 'city_id', 'deadline'],
            array_keys($response->json('errors')),
        );
        $this->assertSame('draft', $this->statusOf($id));
        $this->assertSame(0, $this->auditCount());
    }

    public function test_a_remote_job_needs_no_location(): void
    {
        $id = $this->completeDraft(override: [
            'work_mode' => 'remote', 'province_id' => null, 'city_id' => null, 'subdistrict_id' => null, 'village_id' => null, 'postal_code_id' => null,
        ]);

        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_a_hybrid_or_onsite_job_and_a_job_without_a_work_mode_need_a_province_and_a_city(): void
    {
        foreach (['hybrid', 'onsite', null] as $mode) {
            $id = $this->completeDraft(override: ['work_mode' => $mode, 'province_id' => null, 'city_id' => null, 'subdistrict_id' => null, 'village_id' => null, 'postal_code_id' => null]);

            $this->api('POST', "/company/jobs/{$id}/publish")->assertStatus(422)->assertJsonStructure(['errors' => ['province_id', 'city_id']]);
        }
    }

    public function test_the_description_needs_at_least_50_characters(): void
    {
        $short = $this->completeDraft(override: ['description' => str_repeat('a', 49)]);
        $enough = $this->completeDraft('Enough', ['description' => str_repeat('a', 50)]);

        $this->api('POST', "/company/jobs/{$short}/publish")->assertStatus(422)->assertJsonStructure(['errors' => ['description']]);
        $this->api('POST', "/company/jobs/{$enough}/publish")->assertOk();
    }

    public function test_the_deadline_must_lie_between_today_and_the_limit(): void
    {
        $today = $this->completeDraft('Today', ['deadline' => '2026-10-05']);
        $edge = $this->completeDraft('Edge', ['deadline' => '2027-01-03']);
        $yesterday = $this->completeDraft('Yesterday', ['deadline' => '2026-10-04']);
        $tooFar = $this->completeDraft('Too far', ['deadline' => '2027-01-04']);

        $this->api('POST', "/company/jobs/{$today}/publish")->assertOk();
        $this->api('POST', "/company/jobs/{$edge}/publish")->assertOk();
        $this->api('POST', "/company/jobs/{$yesterday}/publish")->assertStatus(422)->assertJsonStructure(['errors' => ['deadline']]);
        $this->api('POST', "/company/jobs/{$tooFar}/publish")->assertStatus(422)->assertJsonStructure(['errors' => ['deadline']]);
    }

    public function test_the_deadline_limit_is_the_company_setting(): void
    {
        $this->app->make(SettingsStore::class)->setCompanyOverride(JobSettings::MAX_DEADLINE_DAYS, $this->companyId, 30, null);
        $near = $this->completeDraft('Near', ['deadline' => '2026-11-04']);
        $far = $this->completeDraft('Far', ['deadline' => '2026-11-05']);

        $this->api('POST', "/company/jobs/{$near}/publish")->assertOk();
        $this->api('POST', "/company/jobs/{$far}/publish")->assertStatus(422)->assertJsonStructure(['errors' => ['deadline']]);
    }

    public function test_today_is_the_date_in_jakarta_not_in_utc(): void
    {
        // 18:00 UTC on 5 October is 01:00 WIB on 6 October.
        Carbon::setTestNow('2026-10-05 18:00:00');
        $oldToken = $this->adminToken;
        $this->adminToken = $this->tokenFor($this->adminId);
        $yesterdayInJakarta = $this->completeDraft('Late', ['deadline' => '2026-10-05']);
        $todayInJakarta = $this->completeDraft('Fresh', ['deadline' => '2026-10-06']);

        $this->api('POST', "/company/jobs/{$yesterdayInJakarta}/publish")->assertStatus(422)->assertJsonStructure(['errors' => ['deadline']]);
        $this->api('POST', "/company/jobs/{$todayInJakarta}/publish")->assertOk();
        $this->adminToken = $oldToken;
    }

    public function test_only_a_draft_or_a_rejected_job_can_be_published_and_the_status_is_checked_first(): void
    {
        foreach (['pending_review', 'active', 'closed', 'expired', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status);

            $this->api('POST', "/company/jobs/{$id}/publish")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-publishable');
            $this->assertSame($status, $this->statusOf($id));
        }
    }

    public function test_a_rejected_job_can_be_published_again_and_its_reason_is_cleared(): void
    {
        $id = $this->completeDraft();
        DB::table('job_jobs')->where('id', $id)->update(['status' => 'rejected', 'status_reason' => 'Not clear.']);

        $response = $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $this->assertSame('active', $response->json('data.status'));
        $this->assertArrayNotHasKey('status_reason', $response->json('data'));
        $this->assertNull($this->jobRow($id)->status_reason);
    }

    public function test_who_may_publish_follows_who_may_see_the_job(): void
    {
        $id = $this->completeDraft();

        $this->api('POST', "/company/jobs/{$id}/publish", token: $this->recruiterToken)->assertNotFound();
        $this->api('POST', "/company/jobs/{$id}/publish", token: $this->otherAdminToken)->assertNotFound();
        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->adminId, $this->recruiterId]])->assertOk();
        $this->api('POST', "/company/jobs/{$id}/publish", token: $this->recruiterToken)->assertOk();
    }

    public function test_a_suspended_company_can_do_nothing_with_its_jobs(): void
    {
        $id = $this->completeDraft();
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        foreach (['publish', 'close', 'clone'] as $action) {
            $this->api('POST', "/company/jobs/{$id}/{$action}")->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-suspended');
        }
        $this->api('POST', "/company/jobs/{$id}/reopen", ['deadline' => '2026-11-01'])->assertStatus(403);
    }

    // ----- closing -----

    public function test_a_live_job_is_closed_and_audited(): void
    {
        $id = $this->completeDraft();
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $this->api('POST', "/company/jobs/{$id}/close")->assertOk()->assertJsonPath('data.status', 'closed');

        $this->assertSame(['from' => 'active', 'to' => 'closed'], json_decode((string) $this->audit('job.closed')[0]->metadata, true));
    }

    public function test_only_a_live_job_can_be_closed(): void
    {
        foreach (['draft', 'pending_review', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status);

            $this->api('POST', "/company/jobs/{$id}/close")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-active');
            $this->assertSame($status, $this->statusOf($id));
        }
    }

    // ----- reopening -----

    public function test_a_closed_or_expired_job_is_reopened_with_a_new_deadline_and_keeps_its_first_publication_time(): void
    {
        $closed = $this->completeDraft('Closed one');
        $expired = $this->completeDraft('Expired one');
        $this->api('POST', "/company/jobs/{$closed}/publish")->assertOk();
        $this->api('POST', "/company/jobs/{$expired}/publish")->assertOk();
        $this->api('POST', "/company/jobs/{$closed}/close")->assertOk();
        DB::table('job_jobs')->where('id', $expired)->update(['status' => 'expired']);

        Carbon::setTestNow('2026-10-20 09:00:00');
        $this->adminToken = $this->tokenFor($this->adminId);
        $first = $this->api('POST', "/company/jobs/{$closed}/reopen", ['deadline' => '2026-11-30'])->assertOk();
        $this->api('POST', "/company/jobs/{$expired}/reopen", ['deadline' => '2026-11-30'])->assertOk();

        $this->assertSame(['active', '2026-11-30', '2026-10-05T09:00:00+00:00'], [$first->json('data.status'), $first->json('data.deadline'), $first->json('data.published_at')]);
        $this->assertSame('active', $this->statusOf($expired));
        $this->assertSame(['from' => 'closed', 'to' => 'active'], json_decode((string) $this->audit('job.reopened')[0]->metadata, true));
        $this->assertSame(['from' => 'expired', 'to' => 'active'], json_decode((string) $this->audit('job.reopened')[1]->metadata, true));
    }

    public function test_with_approval_required_a_reopened_job_goes_to_review(): void
    {
        $this->requireApproval(forCompany: false);
        $id = $this->completeDraft();
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
        $this->api('POST', "/company/jobs/{$id}/close")->assertOk();
        $this->requireApproval(forCompany: true);

        $this->api('POST', "/company/jobs/{$id}/reopen", ['deadline' => '2026-11-30'])->assertOk()->assertJsonPath('data.status', 'pending_review');

        $this->assertSame('2026-10-05 09:00:00', $this->jobRow($id)->published_at, 'it was live once, so the time stays');
    }

    public function test_the_new_deadline_is_required_and_must_be_in_range(): void
    {
        $id = $this->insertJob('Closed', 'closed', attributes: ['description' => str_repeat('a', 60)]);

        $this->api('POST', "/company/jobs/{$id}/reopen", [])->assertStatus(422)->assertJsonValidationErrors(['deadline']);
        $this->api('POST', "/company/jobs/{$id}/reopen", ['deadline' => 'soon'])->assertStatus(422)->assertJsonValidationErrors(['deadline']);
        $this->assertSame('closed', $this->statusOf($id));
    }

    public function test_a_job_emptied_while_it_was_off_is_not_reopened_until_it_is_complete_again(): void
    {
        $id = $this->completeDraft();
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
        $this->api('POST', "/company/jobs/{$id}/close")->assertOk();
        $this->api('PATCH', "/company/jobs/{$id}", ['description' => null, 'skill_ids' => []])->assertOk();

        $this->api('POST', "/company/jobs/{$id}/reopen", ['deadline' => '2026-11-30'])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:job-incomplete')->assertJsonStructure(['errors' => ['description', 'skill_ids']]);
        $this->assertSame('closed', $this->statusOf($id));

        $this->api('PATCH', "/company/jobs/{$id}", ['description' => str_repeat('b', 60), 'skill_ids' => [$this->skillId('php')]])->assertOk();
        $this->api('POST', "/company/jobs/{$id}/reopen", ['deadline' => '2026-11-30'])->assertOk();
    }

    public function test_only_a_closed_or_an_expired_job_can_be_reopened(): void
    {
        foreach (['draft', 'pending_review', 'active', 'rejected', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status);

            $this->api('POST', "/company/jobs/{$id}/reopen", ['deadline' => '2026-11-30'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-reopenable');
            $this->assertSame($status, $this->statusOf($id));
        }
    }

    // ----- editing a live job -----

    public function test_a_change_that_is_not_core_leaves_a_live_job_live_even_when_approval_is_required(): void
    {
        $id = $this->completeDraft();
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
        $this->requireApproval(forCompany: true);

        $this->api('PATCH', "/company/jobs/{$id}", ['vacancies' => 9, 'deadline' => '2026-12-15', 'skill_ids' => [$this->skillId('go')], 'tags' => ['go'], 'benefit_ids' => []])
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertSame(0, $this->auditCount() - count($this->audit('job.published')));
    }

    public function test_a_core_change_sends_a_live_job_back_to_review_when_approval_is_required(): void
    {
        $changes = [
            'title' => 'A new title', 'description' => str_repeat('Different text. ', 5), 'salary_min' => 12000000, 'salary_max' => 18000000, 'salary_visible' => true,
            'work_mode' => 'onsite', 'address_line' => 'Jl. Lain 2',
        ];

        foreach ($changes as $field => $value) {
            DB::table('job_jobs')->delete();
            $this->requireApproval(forCompany: false);
            $id = $this->completeDraft();
            $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
            $this->requireApproval(forCompany: true);

            $this->api('PATCH', "/company/jobs/{$id}", [$field => $value])->assertOk()->assertJsonPath('data.status', 'pending_review');
        }

        $entry = collect($this->audit('job.sent_for_review'))->last();
        $this->assertSame(['from' => 'active', 'to' => 'pending_review'], json_decode((string) $entry->metadata, true));
        $this->assertSame('user', $entry->actor_type);
    }

    public function test_the_location_counts_as_core(): void
    {
        $this->requireApproval(forCompany: false);
        $id = $this->completeDraft();
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
        $this->requireApproval(forCompany: true);

        $this->api('PATCH', "/company/jobs/{$id}", ['subdistrict_id' => null, 'village_id' => null, 'postal_code_id' => null])->assertOk()->assertJsonPath('data.status', 'pending_review');
    }

    public function test_a_core_change_does_nothing_special_when_approval_is_not_required(): void
    {
        $id = $this->completeDraft();
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'A new title'])->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertSame([], $this->audit('job.sent_for_review'));
    }

    public function test_a_core_change_of_a_closed_or_expired_job_triggers_no_review(): void
    {
        $this->requireApproval(forCompany: true);

        foreach (['closed', 'expired'] as $status) {
            $id = $this->insertJob("Job {$status}", $status);

            $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Changed title'])->assertOk()->assertJsonPath('data.status', $status);
        }

        $this->assertSame([], $this->audit('job.sent_for_review'));
    }

    public function test_a_live_job_must_stay_complete(): void
    {
        $id = $this->completeDraft();
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();

        $this->api('PATCH', "/company/jobs/{$id}", ['description' => null, 'skill_ids' => [], 'category_id' => null, 'deadline' => null])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:job-incomplete')
            ->assertJsonStructure(['errors' => ['description', 'skill_ids', 'category_id', 'deadline']]);

        $this->assertSame('active', $this->statusOf($id));
        $this->assertNotNull($this->jobRow($id)->description);
    }

    public function test_the_deadline_of_a_live_job_is_checked_only_when_it_changes(): void
    {
        $id = $this->completeDraft();
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
        DB::table('job_jobs')->where('id', $id)->update(['deadline' => '2026-10-04']);

        $this->api('PATCH', "/company/jobs/{$id}", ['vacancies' => 2])->assertOk();
        $this->api('PATCH', "/company/jobs/{$id}", ['deadline' => '2026-10-03'])->assertStatus(422)->assertJsonStructure(['errors' => ['deadline']]);
        $this->api('PATCH', "/company/jobs/{$id}", ['deadline' => '2027-06-01'])->assertStatus(422)->assertJsonStructure(['errors' => ['deadline']]);
        $this->api('PATCH', "/company/jobs/{$id}", ['deadline' => '2026-12-01'])->assertOk()->assertJsonPath('data.deadline', '2026-12-01');
    }

    public function test_a_draft_may_stay_incomplete_however_often_it_is_edited(): void
    {
        $id = (int) $this->api('POST', '/company/jobs', ['title' => 'Draft'])->json('data.id');

        $this->api('PATCH', "/company/jobs/{$id}", ['description' => 'Short.'])->assertOk();
        $this->api('PATCH', "/company/jobs/{$id}", ['description' => null])->assertOk();
    }

    // ----- cloning -----

    public function test_a_clone_is_a_new_draft_with_the_content_but_no_deadline_and_only_the_cloner_assigned(): void
    {
        $this->requireApproval(forCompany: false);
        $id = $this->completeDraft();
        $this->api('POST', "/company/jobs/{$id}/publish")->assertOk();
        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$this->adminId, $this->recruiterId]])->assertOk();

        $source = $this->api('GET', "/company/jobs/{$id}")->json('data');
        $response = $this->api('POST', "/company/jobs/{$id}/clone", token: $this->recruiterToken)->assertCreated();
        $copy = $response->json('data');

        $this->assertNotSame($id, $copy['id']);
        $this->assertSame('draft', $copy['status']);
        $this->assertNull($copy['deadline']);
        $this->assertNull($copy['published_at']);
        $this->assertSame($this->recruiterId, $copy['created_by_user_id']);
        $this->assertSame([$this->recruiterId], array_column($copy['assignees'], 'user_id'));

        foreach (['title', 'description', 'category_id', 'role_id', 'job_type_id', 'job_level_id', 'work_time_id', 'experience_level_id', 'education_level_id',
            'vacancies', 'salary_min', 'salary_max', 'salary_period_id', 'salary_visible', 'work_mode', 'province_id', 'city_id', 'subdistrict_id',
            'village_id', 'postal_code_id', 'address_line', 'skills', 'tags', 'benefits'] as $field) {
            $this->assertSame($source[$field], $copy[$field], $field);
        }

        $this->assertSame('active', $this->statusOf($id), 'the source is untouched');
        $this->assertSame('2026-12-31', $this->api('GET', "/company/jobs/{$id}")->json('data.deadline'));
    }

    public function test_any_status_can_be_cloned_but_only_a_job_the_member_may_see(): void
    {
        foreach (['draft', 'pending_review', 'active', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $id = $this->insertJob("Job {$status}", $status);

            $this->api('POST', "/company/jobs/{$id}/clone")->assertCreated()->assertJsonPath('data.status', 'draft');
        }

        $adminOnly = $this->insertJob('Admin only', 'active', [$this->adminId]);
        $theirs = $this->insertJob('Theirs', 'active', [$this->otherAdminId], $this->otherCompanyId);

        $this->api('POST', "/company/jobs/{$adminOnly}/clone", token: $this->recruiterToken)->assertNotFound();
        $this->api('POST', "/company/jobs/{$theirs}/clone")->assertNotFound();
        $this->api('POST', '/company/jobs/999999/clone')->assertNotFound();
    }

    public function test_a_clone_of_a_job_with_tags_reuses_the_tags_and_is_not_audited(): void
    {
        $id = $this->completeDraft();
        $before = DB::table('reference_tags')->count();

        $this->api('POST', "/company/jobs/{$id}/clone")->assertCreated();

        $this->assertSame($before, DB::table('reference_tags')->count());
        $this->assertSame(0, $this->auditCount());
    }

    // ----- expiring -----

    public function test_the_expiry_command_expires_live_jobs_whose_deadline_day_is_over(): void
    {
        $overdue = $this->insertJob('Overdue', 'active', [$this->recruiterId, $this->adminId], attributes: ['deadline' => '2026-10-04']);
        $today = $this->insertJob('Today', 'active', attributes: ['deadline' => '2026-10-05']);
        $future = $this->insertJob('Future', 'active', attributes: ['deadline' => '2026-12-01']);
        $closed = $this->insertJob('Closed', 'closed', attributes: ['deadline' => '2026-09-01']);
        $draft = $this->insertJob('Draft', 'draft', attributes: ['deadline' => '2026-09-01']);
        $noDeadline = $this->insertJob('None', 'active');

        $this->assertSame(0, Artisan::call('job:expire-due'));

        $this->assertSame(['expired', 'active', 'active', 'closed', 'draft', 'active'], array_map(fn (int $id): string => $this->statusOf($id), [$overdue, $today, $future, $closed, $draft, $noDeadline]));
        $this->assertStringContainsString('Expired 1 job.', Artisan::output());
    }

    public function test_the_deadline_day_ends_at_midnight_in_jakarta(): void
    {
        $id = $this->insertJob('Edge', 'active', attributes: ['deadline' => '2026-10-05']);

        Carbon::setTestNow('2026-10-05 16:59:59');
        Artisan::call('job:expire-due');
        $this->assertSame('active', $this->statusOf($id), '23:59:59 WIB is still the deadline day');

        Carbon::setTestNow('2026-10-05 17:00:00');
        Artisan::call('job:expire-due');
        $this->assertSame('expired', $this->statusOf($id), '00:00:00 WIB of the next day');
    }

    public function test_an_expiry_is_audited_as_the_system_and_the_assignees_are_told(): void
    {
        $id = $this->insertJob('Overdue', 'active', [$this->recruiterId, $this->adminId], attributes: ['deadline' => '2026-10-04']);

        Artisan::call('job:expire-due');

        $entry = $this->audit('job.expired')[0];
        $this->assertSame(['system', null, 'job', $id], [$entry->actor_type, $entry->actor_id, $entry->subject_type, (int) $entry->subject_id]);
        $this->assertSame(['from' => 'active', 'to' => 'expired'], json_decode((string) $entry->metadata, true));

        foreach ([$this->recruiterId, $this->adminId] as $userId) {
            $rows = DB::table('notifications')->where('notifiable_id', $userId)->get();
            $this->assertCount(1, $rows);
            $this->assertSame('job.expired', $rows[0]->type);
            $this->assertSame(['job_id' => $id], json_decode((string) $rows[0]->data, true));
        }

        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->secondRecruiterId)->count());
    }

    public function test_running_the_expiry_again_changes_nothing_more(): void
    {
        $this->insertJob('Overdue', 'active', attributes: ['deadline' => '2026-10-04']);

        Artisan::call('job:expire-due');
        Artisan::call('job:expire-due');

        $this->assertStringContainsString('Expired 0 jobs.', Artisan::output());
        $this->assertCount(1, $this->audit('job.expired'));
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_jobs_of_every_company_expire_and_a_closed_or_changed_job_is_left_alone(): void
    {
        $mine = $this->insertJob('Mine', 'active', attributes: ['deadline' => '2026-10-04']);
        $theirs = $this->insertJob('Theirs', 'active', [$this->otherAdminId], $this->otherCompanyId, ['deadline' => '2026-10-03']);
        $closedMeanwhile = $this->insertJob('Closed meanwhile', 'closed', attributes: ['deadline' => '2026-10-02']);

        Artisan::call('job:expire-due');

        $this->assertSame(['expired', 'expired', 'closed'], array_map(fn (int $id): string => $this->statusOf($id), [$mine, $theirs, $closedMeanwhile]));
    }

    public function test_a_large_backlog_is_handled_in_batches(): void
    {
        foreach (range(1, 205) as $number) {
            $this->insertJob("Overdue {$number}", 'active', attributes: ['deadline' => '2026-10-01']);
        }

        Artisan::call('job:expire-due');

        $this->assertStringContainsString('Expired 205 jobs.', Artisan::output());
        $this->assertSame(0, DB::table('job_jobs')->where('status', 'active')->count());
    }

    public function test_the_command_is_scheduled_every_hour_without_overlapping(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'job:expire-due'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}
