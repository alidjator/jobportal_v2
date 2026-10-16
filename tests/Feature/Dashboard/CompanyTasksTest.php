<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-recruiter-workday.md` section 1: the task list of a company member ("Perlu tindakan"). */
final class CompanyTasksTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $closing;

    private int $quiet;

    private int $hidden;

    private int $draft;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->closing = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-10-17', 'published_at' => '2026-10-01 00:00:00']);
        $this->quiet = $this->insertJob('Data Analyst', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31', 'published_at' => '2026-10-01 00:00:00']);
        $this->hidden = $this->insertJob('Designer', 'active', [$this->secondRecruiterId], attributes: ['deadline' => '2026-10-15', 'published_at' => '2026-09-01 00:00:00']);
        $this->draft = $this->insertJob('Planner', 'draft', [$this->recruiterId], attributes: ['created_by_user_id' => $this->recruiterId]);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->applicationId = (int) $this->apply($this->closing)->assertCreated()->json('data.id');
        $this->moveClockTo('2026-10-14 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->secondRecruiterToken = $this->tokenFor($this->secondRecruiterId);
        $this->otherAdminToken = $this->tokenFor($this->otherAdminId);
        $this->seekerToken = $this->tokenFor($this->seekerId);
        Cache::flush();
    }

    private function tasks(?string $token = null): TestResponse
    {
        return $this->api('GET', '/company/tasks', token: $token ?? $this->recruiterToken);
    }

    /** @return array<string, int> */
    private function counts(?string $token = null): array
    {
        $items = $this->tasks($token)->assertOk()->json('data.items');

        return array_combine(array_column($items, 'key'), array_column($items, 'count'));
    }

    public function test_the_list_counts_what_waits_for_the_member_in_order_with_the_query_that_opens_it(): void
    {
        $response = $this->tasks()->assertOk();

        $this->assertSame(
            ['stale_applications', 'my_scorecards', 'my_scoring', 'offers_waiting', 'offers_expiring', 'jobs_closing', 'jobs_draft', 'jobs_without_applicants'],
            array_column($response->json('data.items'), 'key'),
        );
        $this->assertSame('2026-10-14T09:00:00+00:00', $response->json('data.generated_at'));
        $this->assertSame('company/applications?filter[stale]=1', $response->json('data.items.0.filter'));
        $this->assertSame('company/jobs?filter[status]=active&filter[deadline_to]=2026-10-21', $response->json('data.items.5.filter'));

        $this->assertSame(
            ['stale_applications' => 1, 'my_scorecards' => 0, 'my_scoring' => 0, 'offers_waiting' => 0, 'offers_expiring' => 0, 'jobs_closing' => 1, 'jobs_draft' => 1, 'jobs_without_applicants' => 1],
            $this->counts(),
        );
    }

    public function test_a_recruiter_counts_only_the_jobs_assigned_to_them_and_an_admin_counts_every_job(): void
    {
        $admin = $this->counts($this->adminToken);

        $this->assertSame(2, $admin['jobs_closing']);
        $this->assertSame(2, $admin['jobs_without_applicants']);
        $this->assertSame(1, $admin['jobs_draft']);
        $this->assertSame(1, $admin['stale_applications']);

        $second = $this->counts($this->secondRecruiterToken);
        $this->assertSame(0, $second['stale_applications']);
        $this->assertSame(1, $second['jobs_closing']);
        $this->assertSame(0, $second['jobs_draft']);
    }

    public function test_an_item_the_member_may_not_use_is_left_out(): void
    {
        $limited = $this->memberWith(['jobs.create', 'applications.view']);

        $keys = array_column($this->tasks($limited['token'])->assertOk()->json('data.items'), 'key');

        $this->assertNotContains('offers_waiting', $keys);
        $this->assertNotContains('offers_expiring', $keys);
        $this->assertContains('stale_applications', $keys);
        $this->assertContains('jobs_draft', $keys);

        $none = $this->memberWith([], 'none@example.test');
        $this->assertNotContains('stale_applications', array_column($this->tasks($none['token'])->json('data.items'), 'key'));
        $this->assertNotContains('jobs_draft', array_column($this->tasks($none['token'])->json('data.items'), 'key'));
    }

    public function test_only_a_member_of_an_active_company_has_a_list(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/company/tasks')->assertStatus(401);
        $this->api('GET', '/company/tasks', token: $this->seekerToken)->assertStatus(403);
    }

    public function test_the_stale_count_follows_the_company_limit_and_the_stage(): void
    {
        $this->app->make(SettingsStore::class)->setCompanyOverride('recruitment.stale_after_days', $this->companyId, 10, null);
        Cache::flush();
        $this->assertSame(0, $this->counts()['stale_applications']);

        $this->app->make(SettingsStore::class)->removeCompanyOverride('recruitment.stale_after_days', $this->companyId);
        Cache::flush();
        $this->assertSame(1, $this->counts()['stale_applications']);

        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['stage_id' => $this->stageId('assessment'), 'stage_type' => 'assessment', 'stage_entered_at' => '2026-08-01 00:00:00']);
        Cache::flush();
        $this->assertSame(0, $this->counts()['stale_applications']);
    }

    public function test_offers_waiting_and_expiring(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stageId('offer'), 'offer' => $this->offerDetails(['expires_at' => '2026-10-20T09:00:00Z'])], $this->recruiterToken)->assertNoContent();
        DB::table('recruitment_offers')->where('application_id', $this->applicationId)->update(['expires_at' => '2026-10-20 09:00:00', 'status' => 'pending']);
        Cache::flush();

        $counts = $this->counts();
        $this->assertSame(1, $counts['offers_waiting']);
        $this->assertSame(0, $counts['offers_expiring']);

        DB::table('recruitment_offers')->where('application_id', $this->applicationId)->update(['expires_at' => '2026-10-16 09:00:00']);
        Cache::flush();
        $this->assertSame(1, $this->counts()['offers_expiring']);

        DB::table('recruitment_offers')->where('application_id', $this->applicationId)->update(['status' => 'declined']);
        Cache::flush();
        $this->assertSame(0, $this->counts()['offers_waiting']);
    }

    public function test_scorecards_wait_for_the_panel_until_they_are_sent(): void
    {
        $this->moveClockTo('2026-10-05 09:00:00');
        $stage = $this->stageId('interview');
        $schedule = $this->interviewSchedule(['participant_ids' => [$this->adminId]]);
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $schedule], $this->recruiterToken)->assertNoContent();
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();
        $interview = (int) DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->value('id');

        $this->assertSame(0, $this->counts()['my_scorecards'], 'scoring opens when the interview starts');

        $this->moveClockTo('2026-10-08 10:30:00');
        $this->assertSame(1, $this->counts()['my_scorecards']);
        $this->assertSame(1, $this->counts($this->adminToken)['my_scorecards']);
        $this->assertSame(0, $this->counts($this->secondRecruiterToken)['my_scorecards']);

        DB::table('recruitment_interview_scorecards')->insert([
            'interview_id' => $interview, 'member_id' => $this->recruiterId, 'status' => 'submitted', 'recommendation' => 'yes', 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::flush();
        $this->assertSame(0, $this->counts()['my_scorecards']);
        $this->assertSame(1, $this->counts($this->adminToken)['my_scorecards']);
    }

    public function test_long_text_answers_wait_for_their_assessor(): void
    {
        $test = (int) DB::table('assessment_tests')->insertGetId([
            'kind' => 'skill', 'company_id' => $this->companyId, 'title' => 'Case study', 'measured_capabilities' => json_encode([]), 'status' => 'published', 'created_by_user_id' => $this->adminId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $invitation = (int) DB::table('assessment_invitations')->insertGetId([
            'application_id' => $this->applicationId, 'company_id' => $this->companyId, 'job_id' => $this->closing, 'applicant_user_id' => $this->seekerId, 'test_id' => $test,
            'invited_by_user_id' => $this->adminId, 'starts_at' => now()->subDays(3), 'due_at' => now()->addDays(3), 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assessment_invitation_assessors')->insert(['invitation_id' => $invitation, 'user_id' => $this->recruiterId]);
        $attempt = (int) DB::table('assessment_attempts')->insertGetId([
            'invitation_id' => $invitation, 'consented_at' => now(), 'started_at' => now(), 'section_opened_at' => now(), 'ends_at' => now(), 'submitted_at' => now(), 'seed' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assessment_results')->insert([
            'attempt_id' => $attempt, 'status' => 'pending_scoring', 'section_scores' => json_encode([]), 'integrity_tier' => 'none', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(1, $this->counts()['my_scoring']);
        $this->assertSame(0, $this->counts($this->adminToken)['my_scoring']);

        DB::table('assessment_results')->where('attempt_id', $attempt)->update(['status' => 'scored']);
        Cache::flush();
        $this->assertSame(0, $this->counts()['my_scoring']);
    }

    private function stageId(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }
}
