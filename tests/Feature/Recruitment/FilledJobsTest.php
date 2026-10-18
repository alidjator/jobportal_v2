<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-hiring.md` section 2: a job is filled when its hires reach its vacancies; the team is told once and may close it and reject the rest. */
final class FilledJobsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    /** @var list<int> application ids of the job, the first of the base applicant */
    private array $ids = [];

    /** @var list<int> the job seeker of each application */
    private array $seekers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31', 'vacancies' => 2]);
        $this->ids[] = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $this->seekers[] = $this->seekerId;

        foreach (['filled.one', 'filled.two', 'filled.three', 'filled.four'] as $name) {
            $seeker = $this->seeker($name.'@example.test');
            $token = $this->tokenFor($seeker);
            $this->resumeId = $this->completeProfile($token);
            $this->ids[] = (int) $this->apply($this->job, token: $token)->assertCreated()->json('data.id');
            $this->seekers[] = $seeker;
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function hire(int $index): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->ids[$index]}/move", ['stage_id' => $this->stage('hired'), 'hire' => ['join_date' => now('Asia/Jakarta')->addDays(20)->format('Y-m-d')]], $this->recruiterToken);
    }

    /** @param  array<string, mixed>  $body */
    private function fill(array $body, ?string $token = null, ?int $job = null): TestResponse
    {
        return $this->api('POST', '/company/jobs/'.($job ?? $this->job).'/fill', $body, $token ?? $this->adminToken);
    }

    /** @return list<object> */
    private function filledNotifications(): array
    {
        return DB::table('notifications')->where('type', 'job.filled')->get()->all();
    }

    private function stageType(int $index): string
    {
        return (string) DB::table('recruitment_applications')->where('id', $this->ids[$index])->value('stage_type');
    }

    // ----- hires_count and filled -----

    public function test_the_job_list_and_detail_count_the_hires_and_say_when_the_job_is_filled(): void
    {
        $this->api('GET', "/company/jobs/{$this->job}", token: $this->adminToken)->assertOk()->assertJsonPath('data.hires_count', 0)->assertJsonPath('data.filled', false);

        $this->hire(0)->assertOk();
        $this->api('GET', "/company/jobs/{$this->job}", token: $this->adminToken)->assertJsonPath('data.hires_count', 1)->assertJsonPath('data.filled', false);

        $this->hire(1)->assertOk();
        $this->api('GET', "/company/jobs/{$this->job}", token: $this->adminToken)->assertJsonPath('data.hires_count', 2)->assertJsonPath('data.filled', true);

        $row = collect($this->api('GET', '/company/jobs', token: $this->adminToken)->assertOk()->json('data'))->firstWhere('id', $this->job);
        $this->assertSame(2, $row['hires_count']);
        $this->assertTrue($row['filled']);
    }

    public function test_a_job_without_vacancies_is_never_filled_and_gives_no_prompt(): void
    {
        DB::table('job_jobs')->where('id', $this->job)->update(['vacancies' => null]);

        $this->hire(0)->assertOk()->assertJsonPath('meta.job_filled', false);
        $this->hire(1)->assertOk()->assertJsonPath('meta.job_filled', false);

        $this->api('GET', "/company/jobs/{$this->job}", token: $this->adminToken)->assertJsonPath('data.hires_count', 2)->assertJsonPath('data.filled', false);
        $this->assertSame([], $this->filledNotifications());
    }

    // ----- the prompt -----

    public function test_the_hire_that_crosses_the_line_says_so_and_tells_the_assignees_once(): void
    {
        $this->hire(0)->assertOk()->assertJsonPath('meta.job_filled', false);
        $this->assertSame([], $this->filledNotifications());

        $this->hire(1)->assertOk()->assertJsonPath('meta.job_filled', true);

        $notifications = $this->filledNotifications();
        $this->assertCount(1, $notifications);
        $this->assertSame($this->recruiterId, (int) $notifications[0]->notifiable_id);
        $this->assertSame(['job_id' => $this->job, 'job_title' => 'Backend Developer', 'hires' => 2, 'vacancies' => 2], json_decode((string) $notifications[0]->data, true));

        // A later hire is not another prompt.
        $this->hire(2)->assertOk()->assertJsonPath('meta.job_filled', false);
        $this->assertCount(1, $this->filledNotifications());
    }

    public function test_a_move_between_two_hired_stages_is_not_a_new_hire(): void
    {
        $this->hire(0)->assertOk();
        $this->hire(1)->assertOk()->assertJsonPath('meta.job_filled', true);

        $second = DB::table('recruitment_stages')->insertGetId(['company_id' => $this->companyId, 'name' => 'Onboarding', 'type' => 'hired', 'is_default' => false, 'position' => 99, 'created_at' => now(), 'updated_at' => now()]);
        $this->api('POST', "/company/applications/{$this->ids[1]}/move", ['stage_id' => $second, 'hire' => ['join_date' => now('Asia/Jakarta')->addDays(10)->format('Y-m-d')]], $this->recruiterToken)
            ->assertOk()->assertJsonPath('meta.job_filled', false);
        $this->assertCount(1, $this->filledNotifications());
    }

    public function test_a_bulk_move_reports_the_prompt(): void
    {
        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", ['application_ids' => [$this->ids[0]], 'stage_id' => $this->stage('hired'), 'hire' => ['join_date' => now('Asia/Jakarta')->addDays(20)->format('Y-m-d')]], $this->recruiterToken)
            ->assertOk()->assertJsonPath('meta.job_filled', false);

        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", ['application_ids' => [$this->ids[1], $this->ids[2]], 'stage_id' => $this->stage('hired'), 'hire' => ['join_date' => now('Asia/Jakarta')->addDays(20)->format('Y-m-d')]], $this->recruiterToken)
            ->assertOk()->assertJsonPath('meta.job_filled', true)->assertJsonPath('data.0.status', 'moved');

        $this->assertCount(1, $this->filledNotifications());
    }

    public function test_an_accepted_offer_that_fills_the_job_tells_the_assignees(): void
    {
        $this->hire(0)->assertOk();
        $this->api('POST', "/company/applications/{$this->ids[1]}/move", ['stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails()], $this->recruiterToken)->assertOk();
        $this->assertSame([], $this->filledNotifications());

        $this->api('POST', "/me/applications/{$this->ids[1]}/offer/accept", token: $this->tokenFor($this->seekers[1]))->assertOk();

        $this->assertCount(1, $this->filledNotifications());
    }

    // ----- mark as filled -----

    public function test_fill_closes_the_job_and_rejects_the_chosen_applications_and_skips_the_rest_with_a_code(): void
    {
        $this->hire(0)->assertOk();
        $this->hire(1)->assertOk();
        // Application 2 has a pending offer, application 3 is already rejected, application 4 will be rejected; the last one belongs to another job.
        $this->api('POST', "/company/applications/{$this->ids[2]}/move", ['stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails()], $this->recruiterToken)->assertOk();
        $this->api('POST', "/company/applications/{$this->ids[3]}/reject", ['reason' => 'other', 'note' => 'Earlier'], $this->recruiterToken)->assertNoContent();
        $otherJob = $this->insertJob('Frontend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $foreign = (int) $this->apply($otherJob, token: $this->tokenFor($this->seekers[4]))->assertCreated()->json('data.id');

        $response = $this->fill(['close' => true, 'reject_application_ids' => [$this->ids[4], $this->ids[2], $this->ids[3], $foreign], 'note' => 'Filled by an internal candidate'])->assertOk();

        $response->assertJsonPath('meta.job_status', 'closed')->assertJsonCount(4, 'data');
        $this->assertSame([
            ['application_id' => $this->ids[4], 'status' => 'rejected', 'code' => null],
            ['application_id' => $this->ids[2], 'status' => 'skipped', 'code' => 'offer-pending'],
            ['application_id' => $this->ids[3], 'status' => 'skipped', 'code' => 'application-final'],
            ['application_id' => $foreign, 'status' => 'skipped', 'code' => 'application-not-found'],
        ], $response->json('data'));

        $this->assertSame('closed', DB::table('job_jobs')->where('id', $this->job)->value('status'));
        $this->assertSame('rejected', $this->stageType(4));
        $this->assertSame('offer', $this->stageType(2));
        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $foreign)->value('stage_type'));

        $history = DB::table('recruitment_application_stage_history')->where('application_id', $this->ids[4])->orderByDesc('id')->first();
        $this->assertSame('position_filled', $history->rejection_reason);
        $this->assertSame('Filled by an internal candidate', $history->note);

        // The usual rejection notice reaches the rejected candidate only; the team's note never goes to the candidate.
        $this->assertSame(1, DB::table('notifications')->where('type', 'application.rejected')->where('notifiable_id', $this->seekers[4])->count());
        $this->assertStringNotContainsString('internal candidate', (string) DB::table('notifications')->where('notifiable_id', $this->seekers[4])->pluck('data')->implode(' '));
        $this->assertSame(0, DB::table('notifications')->where('type', 'application.rejected')->where('notifiable_id', $this->seekers[2])->count());

        // Audited with the counts only.
        $audit = DB::table('platform_audit_logs')->where('action', 'job.filled')->first();
        $this->assertSame(['closed' => true, 'rejected' => 1, 'skipped' => 3, 'company_id' => $this->companyId], json_decode((string) $audit->metadata, true));
    }

    public function test_fill_can_reject_without_closing_and_can_close_without_rejecting(): void
    {
        $this->fill(['close' => false, 'reject_application_ids' => [$this->ids[3]]])->assertOk()
            ->assertJsonPath('meta.job_status', 'active')->assertJsonPath('data.0.status', 'rejected');
        $this->assertSame('rejected', $this->stageType(3));

        $this->fill(['close' => true, 'reject_application_ids' => []])->assertOk()->assertJsonPath('meta.job_status', 'closed')->assertJsonPath('data', []);
        $this->assertSame('closed', DB::table('job_jobs')->where('id', $this->job)->value('status'));

        // A closed job can still be cleaned up without closing it again.
        $this->fill(['close' => false, 'reject_application_ids' => [$this->ids[4]]])->assertOk()->assertJsonPath('meta.job_status', 'closed')->assertJsonPath('data.0.status', 'rejected');
    }

    public function test_closing_a_job_that_is_not_live_changes_nothing(): void
    {
        $this->fill(['close' => true, 'reject_application_ids' => []])->assertOk();

        $this->fill(['close' => true, 'reject_application_ids' => [$this->ids[3]]])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-active');
        $this->assertSame('applied', $this->stageType(3));
    }

    public function test_fill_checks_the_request_the_cap_and_who_may_do_it(): void
    {
        $this->fill([])->assertStatus(422)->assertJsonValidationErrors(['close', 'reject_application_ids']);
        $this->fill(['close' => false, 'reject_application_ids' => [$this->ids[3], $this->ids[3]]])->assertStatus(422)->assertJsonValidationErrors(['reject_application_ids.0']);
        $this->fill(['close' => false, 'reject_application_ids' => [$this->ids[3]], 'note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-rejection');

        $this->app->make(SettingsStore::class)->setGlobal(RecruitmentSettings::MAX_BULK_APPLICATIONS, 1, null);

        $this->fill(['close' => false, 'reject_application_ids' => [$this->ids[3], $this->ids[4]]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:too-many-applications');
        $this->assertSame('applied', $this->stageType(3));

        $this->fill(['close' => false, 'reject_application_ids' => [$this->ids[3]]], $this->seekerToken)->assertStatus(403);
        $this->fill(['close' => false, 'reject_application_ids' => [$this->ids[3]]], $this->secondRecruiterToken)->assertStatus(404);
        $this->fill(['close' => false, 'reject_application_ids' => [$this->ids[3]]], $this->recruiterToken)->assertOk()->assertJsonPath('data.0.status', 'rejected');
        $this->assertSame('applied', $this->stageType(4));
    }
}
