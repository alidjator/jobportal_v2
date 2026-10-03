<?php

namespace Tests\Feature\Recruitment\JobManagement;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\FeatureTestCase;

class PromoteCloneStatusTest extends FeatureTestCase
{
    use SeedsJobManagement;

    private function slugOf(int $id): string
    {
        return (string) DB::table('jobs')->where('id', $id)->value('slug');
    }

    public function test_endpoints_require_company_auth(): void
    {
        foreach (['promote', 'clone', 'change-status'] as $action) {
            $this->postJson("/api/company/{$action}/job", [])->assertUnauthorized();
        }

        Sanctum::actingAs($this->makeCompanyUser('candidate'));
        $this->postJson('/api/company/promote/job', [])->assertUnauthorized();
    }

    // ---- promote ----

    public function test_promote_featured_consumes_quota_and_sets_flags(): void
    {
        $this->settings(['featured_job_days' => 10]);
        $this->actingAsCompany();
        $plan = $this->makeUserPlan($this->companyId, featured: 2, highlight: 2);
        $job = $this->insertJob($this->companyId, ['highlight' => 1, 'highlight_until' => now()->addDay()->format('Y-m-d')]);

        $this->postJson('/api/company/promote/job', ['slug' => $this->slugOf($job), 'badge' => 'featured'])
            ->assertOk()->assertExactJson(['data' => ['success' => 'Job Promote Successfully']]);

        $row = DB::table('jobs')->find($job);
        $this->assertSame(1, (int) $row->featured);
        $this->assertSame(0, (int) $row->highlight);
        $this->assertSame(now()->addDays(10)->format('Y-m-d'), $row->featured_until);
        $this->assertNull($row->highlight_until);
        $this->assertSame(1, (int) DB::table('user_plans')->where('id', $plan)->value('featured_job_limit'));
        $this->assertSame(2, (int) DB::table('user_plans')->where('id', $plan)->value('highlight_job_limit'));
    }

    public function test_promote_highlight_without_days_has_no_expiry(): void
    {
        $this->settings(['highlight_job_days' => 0]);
        $this->actingAsCompany();
        $plan = $this->makeUserPlan($this->companyId, featured: 0, highlight: 1);
        $job = $this->insertJob($this->companyId);

        $this->postJson('/api/company/promote/job', ['slug' => $this->slugOf($job), 'badge' => 'highlight'])->assertOk();

        $row = DB::table('jobs')->find($job);
        $this->assertSame(1, (int) $row->highlight);
        $this->assertNull($row->highlight_until);
        $this->assertSame(0, (int) DB::table('user_plans')->where('id', $plan)->value('highlight_job_limit'));
    }

    public function test_promote_rejects_exhausted_quota_missing_plan_and_bad_input(): void
    {
        $this->settings();
        $this->actingAsCompany();
        $job = $this->insertJob($this->companyId);
        $slug = $this->slugOf($job);

        $this->postJson('/api/company/promote/job', ['slug' => $slug, 'badge' => 'featured'])
            ->assertStatus(400)->assertJson(['error' => 'You are not authorized to perform this action']);

        $this->makeUserPlan($this->companyId, featured: 0, highlight: 0);
        $this->postJson('/api/company/promote/job', ['slug' => $slug, 'badge' => 'featured'])
            ->assertStatus(400)->assertJson(['error' => 'You have no featured job limit']);
        $this->postJson('/api/company/promote/job', ['slug' => $slug, 'badge' => 'highlight'])
            ->assertStatus(400)->assertJson(['error' => 'You have no highlight job limit']);

        $this->postJson('/api/company/promote/job', [])->assertStatus(422)->assertJsonValidationErrors(['slug', 'badge']);
        $this->postJson('/api/company/promote/job', ['slug' => $slug, 'badge' => 'gold'])->assertStatus(422)->assertJsonValidationErrors(['badge']);
        $this->postJson('/api/company/promote/job', ['slug' => 'missing-'.random_int(1, 999999), 'badge' => 'featured'])
            ->assertNotFound()->assertJson(['error' => 'Job not found']);
    }

    public function test_promote_foreign_job_is_404_and_quota_untouched(): void
    {
        $this->settings();
        $this->makeCompanyUser();
        $foreign = $this->insertJob($this->companyId);
        $this->actingAsCompany();
        $plan = $this->makeUserPlan($this->companyId, featured: 3, highlight: 3);

        $this->postJson('/api/company/promote/job', ['slug' => $this->slugOf($foreign), 'badge' => 'featured'])->assertNotFound();

        $this->assertSame(3, (int) DB::table('user_plans')->where('id', $plan)->value('featured_job_limit'));
        $this->assertSame(0, (int) DB::table('jobs')->where('id', $foreign)->value('featured'));
    }

    // ---- clone ----

    public function test_clone_copies_job_with_new_slug_and_uses_job_quota(): void
    {
        $this->settings();
        $this->actingAsCompany();
        $plan = $this->makeUserPlan($this->companyId, jobs: 3, featured: 1, highlight: 0);
        $job = $this->insertJob($this->companyId, [
            'title' => 'Clone me '.$this->companyId, 'featured' => 1, 'highlight' => 1, 'total_views' => 99,
            'job_order_id' => (string) random_int(1000, 99999999), 'description' => 'desc',
        ]);

        $response = $this->postJson('/api/company/clone/job', ['slug' => $this->slugOf($job)])->assertOk();

        $clone = $response->json('data.data.data');
        $this->assertSame('Job Cloned Successfully', $response->json('data.data.message'));
        $this->assertNotSame($job, $clone['id']);
        $this->assertSame('desc', $clone['description']);
        $this->assertSame($this->companyId, $clone['company_id']);
        $this->assertNotSame($this->slugOf($job), $clone['slug']);
        $this->assertTrue($clone['featured']);          // kuota featured tersedia
        $this->assertFalse($clone['highlight']);        // kuota highlight habis
        $this->assertSame(0, $clone['total_views']);
        $this->assertNull($clone['job_order_id']);

        $row = DB::table('user_plans')->find($plan);
        $this->assertSame([2, 0, 0], [(int) $row->job_limit, (int) $row->featured_job_limit, (int) $row->highlight_job_limit]);
    }

    public function test_clone_rejected_when_plan_limit_reached_or_missing(): void
    {
        $this->settings();
        $this->actingAsCompany();
        $job = $this->insertJob($this->companyId);
        $slug = $this->slugOf($job);

        $this->postJson('/api/company/clone/job', ['slug' => $slug])->assertStatus(400);

        $this->makeUserPlan($this->companyId, jobs: 0);
        $this->postJson('/api/company/clone/job', ['slug' => $slug])
            ->assertStatus(400)->assertJson(['error' => 'You have reached your plan limit. Please upgrade your plan.']);
        $this->assertSame(1, DB::table('jobs')->where('company_id', $this->companyId)->count());

        $this->postJson('/api/company/clone/job', [])->assertStatus(422)->assertJsonValidationErrors(['slug']);
    }

    public function test_clone_foreign_job_is_404(): void
    {
        $this->settings();
        $this->makeCompanyUser();
        $foreign = $this->insertJob($this->companyId);
        $this->actingAsCompany();
        $plan = $this->makeUserPlan($this->companyId, jobs: 3);

        $this->postJson('/api/company/clone/job', ['slug' => $this->slugOf($foreign)])->assertNotFound();
        $this->assertSame(3, (int) DB::table('user_plans')->where('id', $plan)->value('job_limit'));
        $this->postJson('/api/company/clone/job', ['slug' => 'nope-'.random_int(1, 99999)])->assertNotFound();
    }

    // ---- change status ----

    public function test_company_can_close_and_reopen_job(): void
    {
        $this->settings();
        $this->actingAsCompany();
        $job = $this->insertJob($this->companyId, ['status' => 'active']);
        $slug = $this->slugOf($job);

        $this->postJson('/api/company/change-status/job', ['slug' => $slug, 'status' => 'expired'])
            ->assertOk()->assertExactJson(['data' => ['success' => 'Job status updated successfully']]);
        $this->assertSame('expired', DB::table('jobs')->where('id', $job)->value('status'));

        $this->postJson('/api/company/change-status/job', ['slug' => $slug, 'status' => 'active'])->assertOk();
        $this->assertSame('active', DB::table('jobs')->where('id', $job)->value('status'));
    }

    public function test_status_transition_rules(): void
    {
        $this->settings(['job_auto_approved' => 0]);
        $this->actingAsCompany();
        $pending = $this->insertJob($this->companyId, ['status' => 'pending']);
        $pastDeadline = $this->insertJob($this->companyId, ['status' => 'expired', 'deadline' => now()->subDays(2)->format('Y-m-d')]);
        $active = $this->insertJob($this->companyId, ['status' => 'active']);

        // pending tidak bisa mengaktifkan diri sendiri tanpa persetujuan admin
        $this->postJson('/api/company/change-status/job', ['slug' => $this->slugOf($pending), 'status' => 'active'])
            ->assertStatus(400)->assertJson(['error' => 'Invalid job status']);
        $this->assertSame('pending', DB::table('jobs')->where('id', $pending)->value('status'));
        // deadline lewat tidak bisa dibuka lagi
        $this->postJson('/api/company/change-status/job', ['slug' => $this->slugOf($pastDeadline), 'status' => 'active'])
            ->assertStatus(400);
        // kembali ke pending tidak diizinkan
        $this->postJson('/api/company/change-status/job', ['slug' => $this->slugOf($active), 'status' => 'pending'])
            ->assertStatus(400);

        DB::table('settings')->update(['job_auto_approved' => 1]);
        $this->postJson('/api/company/change-status/job', ['slug' => $this->slugOf($pending), 'status' => 'active'])->assertOk();
    }

    public function test_change_status_validates_enum_and_ownership(): void
    {
        $this->settings();
        $this->makeCompanyUser();
        $foreign = $this->insertJob($this->companyId);
        $this->actingAsCompany();
        $mine = $this->insertJob($this->companyId);

        $this->postJson('/api/company/change-status/job', [])->assertStatus(422)->assertJsonValidationErrors(['slug', 'status']);
        $this->postJson('/api/company/change-status/job', ['slug' => $this->slugOf($mine), 'status' => 'deleted'])
            ->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->postJson('/api/company/change-status/job', ['slug' => $this->slugOf($foreign), 'status' => 'expired'])
            ->assertNotFound()->assertJson(['error' => 'Job not found']);
        $this->assertSame('active', DB::table('jobs')->where('id', $foreign)->value('status'));
    }
}
