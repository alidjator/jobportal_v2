<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class DashboardTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function mine(?string $token = null): TestResponse
    {
        return $this->api('GET', '/me/dashboard', [], $token ?? $this->seekerToken);
    }

    private function company(?string $token = null): TestResponse
    {
        return $this->api('GET', '/company/dashboard', [], $token ?? $this->adminToken);
    }

    /** @param  list<string>  $permissions */
    private function adminAccountToken(array $permissions, string $email = 'ops@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    private function platform(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/admin/dashboard');
    }

    private function applicationOf(int $jobId): int
    {
        return (int) DB::table('recruitment_applications')->where('job_id', $jobId)->where('user_id', $this->seekerId)->value('id');
    }

    private function interview(int $applicationId, string $status = 'confirmed', string $startsIn = '+2 days'): void
    {
        $start = now()->modify($startsIn);

        DB::table('recruitment_interviews')->insert([
            'application_id' => $applicationId, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'format' => 'online', 'interviewer_id' => $this->recruiterId,
            'created_by' => $this->recruiterId, 'status' => $status, 'active_key' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function job(string $title, string $status, int $assignee): int
    {
        return $this->insertJob($title, $status, [$assignee], attributes: ['deadline' => '2026-12-31']);
    }

    // ----- job seeker -----

    public function test_the_job_seeker_dashboard_summarises_their_own_data(): void
    {
        $job = $this->liveJob();
        $this->apply($job)->assertCreated();
        $this->interview($this->applicationOf($job));
        $this->interview($this->applicationOf($job), 'cancelled', '+3 days');
        $this->interview($this->applicationOf($job), 'confirmed', '-1 day');

        DB::table('jobinvitation_invitations')->insert(['company_id' => $this->companyId, 'job_id' => $this->job('Other job', 'active', $this->adminId), 'user_id' => $this->seekerId, 'invited_by_user_id' => $this->adminId, 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()]);

        foreach ([null, '2026-10-04 08:00:00'] as $readAt) {
            DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => 'user', 'notifiable_id' => $this->seekerId, 'data' => '{}', 'read_at' => $readAt, 'created_at' => now(), 'updated_at' => now()]);
        }

        $conversation = DB::table('messaging_conversations')->insertGetId(['company_id' => $this->companyId, 'job_seeker_user_id' => $this->seekerId, 'started_by_user_id' => $this->adminId, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('messaging_conversation_reads')->insert(['conversation_id' => $conversation, 'user_id' => $this->seekerId, 'unread_since' => now(), 'created_at' => now(), 'updated_at' => now()]);

        foreach ([[$this->companyId, '2026-10-04'], [$this->companyId, '2026-10-03'], [$this->otherCompanyId, '2026-09-20'], [$this->otherCompanyId, '2026-08-01']] as $i => [$company, $day]) {
            DB::table('jobseeker_profile_views')->insert(['user_id' => $this->seekerId, 'company_id' => $company, 'member_id' => $this->adminId + $i, 'kind' => 'profile', 'day' => $day, 'created_at' => now()]);
        }

        $response = $this->mine()->assertOk();
        $cards = $response->json('data.cards');

        $this->assertSame(['applications', 'interviews', 'invitations', 'messages', 'notifications', 'profile'], array_keys($cards));
        $this->assertSame('2026-10-05T09:00:00Z', $response->json('data.generated_at'));

        $applications = $cards['applications'];
        $this->assertSame(1, $applications['by_stage']['applied']);
        $this->assertSame(0, $applications['by_stage']['rejected']);
        $this->assertSame(1, $applications['active']);
        $this->assertSame([$job], array_column($applications['recent'], 'job_id'));
        $this->assertSame('Backend Developer', $applications['recent'][0]['job_title']);
        $this->assertSame('applied', $applications['recent'][0]['stage_type']);

        $this->assertCount(1, $cards['interviews']['upcoming']);
        $this->assertSame('confirmed', $cards['interviews']['upcoming'][0]['status']);
        $this->assertSame(['new' => 1, 'pending' => 1], $cards['invitations']);
        $this->assertSame(['unread_conversations' => 1], $cards['messages']);
        $this->assertSame(['unread' => 1], $cards['notifications']);

        // The profile has a headline, a phone, a location, one experience and one resume: four of the eight sections.
        $this->assertSame(50, $cards['profile']['completeness_percent']);
        $this->assertSame(['headline_summary', 'education', 'skills', 'languages'], $cards['profile']['empty_sections']);
        $this->assertSame(2, $cards['profile']['viewed_by_companies']);
    }

    public function test_the_completeness_never_counts_the_personal_details_or_the_photo(): void
    {
        DB::table('jobseeker_profiles')->where('user_id', $this->seekerId)->update(['birth_date' => null, 'gender' => null, 'religion_id' => null, 'nationality_id' => null, 'marital_status' => null]);
        $before = $this->mine()->json('data.cards.profile.completeness_percent');

        $this->app->make('cache')->flush();
        DB::table('jobseeker_profiles')->where('user_id', $this->seekerId)->update(['birth_date' => '1998-05-10', 'gender' => 'female', 'marital_status' => 'single']);

        $this->assertSame($before, $this->mine()->json('data.cards.profile.completeness_percent'));
    }

    public function test_a_job_seeker_without_a_profile_row_has_every_section_empty(): void
    {
        $other = $this->seeker('new@example.test', name: 'New Seeker');

        $profile = $this->mine($this->tokenFor($other))->assertOk()->json('data.cards.profile');

        $this->assertSame(0, $profile['completeness_percent']);
        $this->assertCount(8, $profile['empty_sections']);
    }

    public function test_a_dashboard_is_kept_for_two_minutes(): void
    {
        $first = $this->mine()->assertOk()->json('data');
        $this->assertSame(0, $first['cards']['notifications']['unread']);

        DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => 'user', 'notifiable_id' => $this->seekerId, 'data' => '{}', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()]);

        Carbon::setTestNow(now()->addSeconds(100));
        $this->assertSame($first, $this->mine()->json('data'));

        Carbon::setTestNow(now()->addSeconds(30));
        $later = $this->mine()->json('data');

        $this->assertSame(1, $later['cards']['notifications']['unread']);
        $this->assertNotSame($first['generated_at'], $later['generated_at']);
    }

    // ----- company -----

    /** @return array{0: int, 1: int, 2: int} the job of the recruiter, the job of the admin, the draft of the recruiter */
    private function companyJobs(): array
    {
        $mine = $this->job('Backend Developer', 'active', $this->recruiterId);
        $admins = $this->job('Frontend Developer', 'active', $this->adminId);
        $draft = $this->job('Designer', 'draft', $this->recruiterId);

        $this->apply($mine)->assertCreated();
        $this->apply($admins)->assertCreated();

        return [$mine, $admins, $draft];
    }

    public function test_a_company_admin_sees_every_job_and_a_recruiter_only_the_assigned_ones(): void
    {
        [$mine, $admins] = $this->companyJobs();
        $this->interview($this->applicationOf($mine));
        $this->interview($this->applicationOf($admins), 'pending', '+1 day');
        DB::table('recruitment_offers')->insert([
            'application_id' => $this->applicationOf($admins), 'position' => 'Dev', 'salary' => 1_000_000, 'start_date' => '2026-11-01', 'expires_at' => now()->addDays(5), 'created_by' => $this->adminId,
            'status' => 'pending', 'active_key' => $this->applicationOf($admins), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $admin = $this->company()->assertOk()->json('data.cards');
        $recruiter = $this->company($this->recruiterToken)->assertOk()->json('data.cards');

        $this->assertSame(['applications', 'job_performance', 'jobs', 'messages', 'notifications', 'schedule'], array_keys($admin));

        $this->assertSame(3, $admin['jobs']['total']);
        $this->assertSame(2, $admin['jobs']['by_status']['active']);
        $this->assertSame(1, $admin['jobs']['by_status']['draft']);
        $this->assertSame(0, $admin['jobs']['by_status']['taken_down']);
        $this->assertSame(2, $recruiter['jobs']['total']);
        $this->assertSame(1, $recruiter['jobs']['by_status']['active']);

        $this->assertSame(2, $admin['applications']['new']);
        $this->assertSame(2, $admin['applications']['by_stage']['applied']);
        $this->assertSame(1, $recruiter['applications']['new']);

        $this->assertCount(2, $admin['schedule']['interviews']);
        $this->assertSame(1, $admin['schedule']['offers_waiting']);
        $this->assertCount(1, $recruiter['schedule']['interviews']);
        $this->assertSame(0, $recruiter['schedule']['offers_waiting']);
    }

    public function test_the_job_performance_card_counts_views_and_applications_per_job(): void
    {
        [$mine, $admins, $draft] = $this->companyJobs();

        foreach ([[$mine, 'u1', '2026-10-05'], [$mine, 'u2', '2026-10-05'], [$mine, 'u3', '2026-10-04'], [$admins, 'u1', '2026-10-05'], [$mine, 'u9', '2026-08-01']] as [$job, $key, $day]) {
            DB::table('job_views')->insert(['job_id' => $job, 'viewer_key' => $key, 'day' => $day, 'created_at' => now()]);
        }

        $performance = $this->company()->assertOk()->json('data.cards.job_performance');

        // The draft has neither views nor applications, so it is left out; the most viewed job comes first; a view older than 30 days does not count.
        $this->assertSame([$mine, $admins], array_column($performance['jobs'], 'job_id'));
        $this->assertSame(3, $performance['jobs'][0]['views']);
        $this->assertSame(1, $performance['jobs'][0]['applications']);
        $this->assertEqualsWithDelta(0.3333, $performance['jobs'][0]['applications_per_view'], 0.0001);
        $this->assertNotContains($draft, array_column($performance['jobs'], 'job_id'));

        $this->assertCount(30, $performance['views_daily']);
        $this->assertSame(['day' => '2026-10-05', 'count' => 3], $performance['views_daily'][29]);
        $this->assertSame(['day' => '2026-10-04', 'count' => 1], $performance['views_daily'][28]);
        $this->assertSame(['day' => '2026-10-05', 'count' => 2], $performance['applications_daily'][29]);

        $recruiter = $this->company($this->recruiterToken)->assertOk()->json('data.cards.job_performance');

        $this->assertSame([$mine], array_column($recruiter['jobs'], 'job_id'));
        $this->assertSame(2, $recruiter['views_daily'][29]['count']);
        $this->assertSame(1, $recruiter['applications_daily'][29]['count']);
    }

    public function test_the_performance_of_one_job_has_its_own_totals_and_daily_series(): void
    {
        [$mine, $admins] = $this->companyJobs();

        foreach ([[$mine, 'u1', '2026-10-05'], [$mine, 'u2', '2026-10-05'], [$mine, 'u3', '2026-10-04'], [$admins, 'u1', '2026-10-05'], [$mine, 'u9', '2026-08-01']] as [$job, $key, $day]) {
            DB::table('job_views')->insert(['job_id' => $job, 'viewer_key' => $key, 'day' => $day, 'created_at' => now()]);
        }

        $data = $this->api('GET', "/company/jobs/{$mine}/performance", [], $this->adminToken)->assertOk()->json('data');

        $this->assertSame($mine, $data['job_id']);
        $this->assertSame('active', $data['status']);
        $this->assertSame(3, $data['views']);
        $this->assertSame(1, $data['applications']);
        $this->assertEqualsWithDelta(0.3333, $data['applications_per_view'], 0.0001);
        $this->assertSame('2026-10-05', $data['to']);
        $this->assertCount(30, $data['views_daily']);
        $this->assertSame(['day' => '2026-10-05', 'count' => 2], $data['views_daily'][29]);
        $this->assertSame(['day' => '2026-10-04', 'count' => 1], $data['views_daily'][28]);
        $this->assertSame(['day' => '2026-10-05', 'count' => 1], $data['applications_daily'][29]);

        $quiet = $this->api('GET', "/company/jobs/{$this->companyJobs()[2]}/performance", [], $this->adminToken)->assertOk()->json('data');

        $this->assertSame(0, $quiet['views']);
        $this->assertNull($quiet['applications_per_view']);
    }

    public function test_the_performance_of_a_job_follows_the_job_access_rules(): void
    {
        [$mine, $admins] = $this->companyJobs();

        $this->api('GET', "/company/jobs/{$mine}/performance", [], $this->recruiterToken)->assertOk();
        $this->api('GET', "/company/jobs/{$admins}/performance", [], $this->recruiterToken)->assertNotFound();
        $this->api('GET', "/company/jobs/{$mine}/performance", [], $this->otherAdminToken)->assertNotFound();
        $this->api('GET', "/company/jobs/{$mine}/performance", [], $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
    }

    public function test_a_job_without_views_has_no_rate(): void
    {
        [$mine] = $this->companyJobs();

        $jobs = $this->company()->assertOk()->json('data.cards.job_performance.jobs');
        $row = $jobs[array_search($mine, array_column($jobs, 'job_id'), true)];

        $this->assertSame(0, $row['views']);
        $this->assertNull($row['applications_per_view']);
    }

    public function test_another_company_sees_nothing_of_it(): void
    {
        $this->companyJobs();

        $cards = $this->company($this->otherAdminToken)->assertOk()->json('data.cards');

        $this->assertSame(0, $cards['jobs']['total']);
        $this->assertSame(0, $cards['applications']['new']);
        $this->assertSame([], $cards['job_performance']['jobs']);
        $this->assertSame([], $cards['schedule']['interviews']);
    }

    public function test_each_dashboard_refuses_the_wrong_kind_of_account(): void
    {
        $this->flushHeaders();
        $this->mine($this->adminToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
        $this->company($this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/me/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/company/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/company/jobs/1/performance')->assertStatus(401);
        $this->platform($this->seekerToken)->assertStatus(401);
    }

    public function test_a_suspended_company_has_no_dashboard(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->company()->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
    }

    // ----- admin -----

    public function test_the_admin_dashboard_needs_its_permission(): void
    {
        $this->platform($this->adminAccountToken(['jobs.manage']))->assertStatus(403);
        $this->platform($this->adminAccountToken(['dashboard.view'], 'viewer@example.test'))->assertOk();
    }

    public function test_the_work_queues_appear_only_with_the_permission_of_their_module(): void
    {
        $base = ['applications', 'companies', 'job_seekers', 'jobs', 'operations'];

        $plain = $this->platform($this->adminAccountToken(['dashboard.view']))->assertOk()->json('data.cards');
        $jobs = $this->platform($this->adminAccountToken(['dashboard.view', 'jobs.manage'], 'jobs@example.test'))->assertOk()->json('data.cards');
        $all = $this->platform($this->adminAccountToken(['dashboard.view', 'jobs.manage', 'companies.manage'], 'all@example.test'))->assertOk()->json('data.cards');

        $this->assertSame($base, array_keys($plain));
        $this->assertSame(['applications', 'companies', 'job_seekers', 'jobs', 'jobs_queue', 'operations'], array_keys($jobs));
        $this->assertSame(['applications', 'companies', 'companies_queue', 'job_seekers', 'jobs', 'jobs_queue', 'operations'], array_keys($all));
    }

    public function test_the_admin_dashboard_counts_the_platform_and_the_queues(): void
    {
        $this->companyJobs();
        $this->job('Waiting job', 'pending_review', $this->adminId);
        $this->seeker('second@example.test', name: 'Second Seeker');
        $gone = $this->seeker('gone@example.test', name: 'Gone Seeker');
        DB::table('identity_users')->where('id', $gone)->update(['anonymized_at' => now()]);
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['status' => 'suspended']);

        $type = DB::table('company_document_types')->insertGetId(['slug' => 'dashboard-test-document', 'name' => json_encode(['en' => 'NIB']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_documents')->insert([
            'company_id' => $this->companyId, 'document_type_id' => $type, 'status' => 'pending', 'file_path' => 'x/nib.pdf', 'original_name' => 'nib.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 10, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $cards = $this->platform($this->adminAccountToken(['dashboard.view', 'jobs.manage', 'companies.manage']))->assertOk()->json('data.cards');

        $this->assertSame(2, $cards['job_seekers']['total']);
        $this->assertSame(2, $cards['job_seekers']['daily'][29]['count']);
        $this->assertSame(['total' => 2, 'active' => 1], ['total' => $cards['companies']['total'], 'active' => $cards['companies']['active']]);
        $this->assertSame(4, $cards['jobs']['total']);
        $this->assertSame(2, $cards['jobs']['live']);
        $this->assertSame(4, $cards['jobs']['daily'][29]['count']);
        $this->assertCount(30, $cards['jobs']['daily']);
        $this->assertSame(2, $cards['applications']['total']);
        $this->assertSame(2, $cards['applications']['daily'][29]['count']);
        $this->assertSame(['waiting_for_approval' => 1], $cards['jobs_queue']);
        $this->assertSame(['waiting_for_review' => 1, 'without_admin' => 0, 'without_admin_companies' => []], $cards['companies_queue']);
    }

    public function test_the_companies_card_lists_the_companies_without_a_company_admin(): void
    {
        $this->companyJobs();
        $token = $this->adminAccountToken(['dashboard.view', 'companies.manage']);

        DB::table('company_members')->where('user_id', $this->otherAdminId)->delete();
        $queue = function () use ($token) {
            $this->app->make('cache')->flush();

            return $this->platform($token)->assertOk()->json('data.cards.companies_queue');
        };

        $this->assertSame(1, $queue()['without_admin']);
        $this->assertSame([['id' => $this->otherCompanyId, 'name' => DB::table('company_companies')->where('id', $this->otherCompanyId)->value('name')]], $queue()['without_admin_companies']);

        $invitation = ['company_id' => $this->otherCompanyId, 'email' => 'new@example.test', 'role' => 'company_admin', 'token_hash' => str_repeat('a', 64), 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        DB::table('company_invitations')->insert([...$invitation, 'expires_at' => now()->addDays(3)]);
        $this->assertSame(0, $queue()['without_admin'], 'A company whose first admin can still accept is not counted.');

        DB::table('company_invitations')->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, $queue()['without_admin'], 'An expired invitation does not count.');

        DB::table('company_invitations')->update(['expires_at' => now()->addDays(3), 'revoked_at' => now()]);
        $this->assertSame(1, $queue()['without_admin'], 'A revoked invitation does not count.');

        DB::table('company_invitations')->delete();
        DB::table('company_invitations')->insert([...$invitation, 'role' => 'member', 'expires_at' => now()->addDays(3)]);
        $this->assertSame(1, $queue()['without_admin'], 'An invitation for a recruiter does not count.');
    }

    public function test_the_operations_card_counts_failed_and_waiting_queue_jobs(): void
    {
        foreach ([['default', now()->subHours(2)], ['bulk', now()->subHours(3)], ['bulk', now()->subHours(1)], ['default', now()->subHours(30)]] as [$queue, $at]) {
            DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => $queue, 'payload' => '{}', 'exception' => 'boom', 'failed_at' => $at]);
        }

        foreach ([['bulk', null], ['bulk', null], ['critical', now()->timestamp]] as [$queue, $reserved]) {
            DB::table('jobs')->insert(['queue' => $queue, 'payload' => '{}', 'attempts' => 0, 'reserved_at' => $reserved, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        }

        $operations = $this->platform($this->adminAccountToken(['dashboard.view']))->assertOk()->json('data.cards.operations');

        $this->assertSame(3, $operations['failed_last_24_hours']);
        $this->assertSame(['bulk' => 2, 'default' => 1], $operations['failed_by_queue']);
        $this->assertSame(2, $operations['waiting']);
        $this->assertSame(['bulk' => 2], $operations['waiting_by_queue']);
    }
}
