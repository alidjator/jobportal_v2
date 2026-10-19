<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Application\SendInterviewReminders;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class InterviewsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function toInterviewStage(?array $schedule = null): TestResponse
    {
        $body = ['stage_id' => $this->stage('interview')];

        if ($schedule !== null) {
            $body['interview'] = $schedule;
        }

        return $this->api('POST', "/company/applications/{$this->applicationId}/move", $body, $this->recruiterToken);
    }

    private function schedule(array $overrides = [], ?string $token = null, ?int $id = null): TestResponse
    {
        return $this->api('PUT', '/company/applications/'.($id ?? $this->applicationId).'/interview', $this->interviewSchedule($overrides), $token ?? $this->recruiterToken);
    }

    private function mine(string $action, array $body = [], ?string $method = 'POST'): TestResponse
    {
        return $this->api($method, "/me/applications/{$this->applicationId}/interview".($action === '' ? '' : "/{$action}"), $body, $this->seekerToken);
    }

    private function team(string $action): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->applicationId}/interview/{$action}", [], $this->recruiterToken);
    }

    private function inTheFuture(int $days): string
    {
        return now()->addDays($days)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function notificationTypes(int $userId): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', 'interview.%')->pluck('type')->all();
        sort($types);

        return $types;
    }

    // ----- moving to an interview stage -----

    public function test_a_move_to_an_interview_stage_needs_a_schedule_and_saves_it_with_the_move(): void
    {
        $this->toInterviewStage()->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:schedule-required');
        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'));

        $this->toInterviewStage($this->interviewSchedule(['format' => 'onsite', 'location' => 'Room 2']))->assertOk();

        $this->assertSame('interview', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'));
        $row = DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->first();
        $this->assertSame(['pending', 'onsite', 'Room 2', null, $this->applicationId], [$row->status, $row->format, $row->location, $row->meeting_url, (int) $row->active_key]);
        $this->assertSame(['interview.scheduled'], $this->notificationTypes($this->seekerId));
    }

    public function test_an_invalid_schedule_stops_the_move(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['starts_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z')]))
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');
        $this->toInterviewStage($this->interviewSchedule(['interviewer_id' => $this->memberWith(['applications.view'], 'viewer@example.test')['id']]))
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interviewer');
        $this->toInterviewStage($this->interviewSchedule(['interviewer_id' => $this->otherAdminId]))->assertStatus(422);
        $this->toInterviewStage(['starts_at' => 'tomorrow'])->assertStatus(422);

        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'));
        $this->assertSame(0, DB::table('recruitment_interviews')->count());
    }

    public function test_a_company_admin_may_be_the_interviewer_and_an_assigned_recruiter_too(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['interviewer_id' => $this->adminId]))->assertOk();

        $this->schedule(['interviewer_id' => $this->recruiterId])->assertOk()->assertJsonPath('data.interviewer.name', 'Rina Rekruter');
    }

    public function test_a_schedule_for_another_stage_is_refused(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening'), 'interview' => $this->interviewSchedule()], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-schedule');
    }

    public function test_moving_between_interview_stages_keeps_the_active_interview_and_needs_no_new_schedule(): void
    {
        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'HR interview', 'type' => 'interview'], $this->adminToken)->assertCreated()->json('data.id');
        $this->toInterviewStage($this->interviewSchedule())->assertOk();

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $extra], $this->recruiterToken)->assertOk();

        $this->assertSame(1, DB::table('recruitment_interviews')->count());
    }

    // ----- scheduling and replacing -----

    public function test_an_interview_can_be_scheduled_and_replaced_only_in_an_interview_stage(): void
    {
        $this->schedule()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:not-in-interview-stage');

        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        DB::table('recruitment_interviews')->delete();

        $first = $this->schedule()->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.proposals_left', 2)->json('data.id');
        $second = $this->schedule(['starts_at' => $this->inTheFuture(5)])->assertOk()->json('data.id');

        $this->assertNotSame($first, $second);
        $this->assertSame('cancelled', DB::table('recruitment_interviews')->where('id', $first)->value('status'));
        $this->assertSame(1, DB::table('recruitment_interviews')->whereNotNull('active_key')->count());
        $this->api('GET', "/company/applications/{$this->applicationId}/interviews", token: $this->recruiterToken)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $second);
        $this->assertContains('interview.rescheduled', $this->notificationTypes($this->seekerId));
    }

    public function test_only_the_members_who_may_see_the_job_can_schedule(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();

        $this->schedule(token: $this->seekerToken)->assertStatus(403);
        $this->schedule(token: $this->secondRecruiterToken)->assertStatus(404);
        $this->schedule(token: $this->otherAdminToken)->assertStatus(404);
        $this->schedule(id: 999999)->assertStatus(404);
        $this->api('GET', "/company/applications/{$this->applicationId}/interviews", token: $this->secondRecruiterToken)->assertStatus(404);
        $this->schedule(token: $this->adminToken)->assertOk();
    }

    public function test_the_fields_are_validated(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();

        foreach ([['starts_at' => '2026-12-01 10:00'], ['duration_minutes' => 'x'], ['format' => 'phone'], ['meeting_url' => 'not a url'], ['interviewer_id' => 0]] as $bad) {
            $this->schedule($bad)->assertStatus(422);
        }

        $this->schedule(['duration_minutes' => 1])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');
        $this->schedule(['format' => 'onsite'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');
    }

    public function test_a_job_that_was_taken_down_cannot_be_scheduled(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        DB::table('job_jobs')->where('id', $this->job)->update(['status' => 'taken_down']);

        $this->schedule()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-taken-down');
    }

    // ----- the job seeker answers -----

    public function test_the_job_seeker_sees_confirms_and_the_team_is_told(): void
    {
        $this->mine('', method: 'GET')->assertOk()->assertJsonPath('data', null);
        $this->toInterviewStage($this->interviewSchedule())->assertOk();

        $this->mine('', method: 'GET')->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.interviewer.name', 'Rina Rekruter')->assertJsonMissingPath('data.created_by');
        $this->mine('confirm')->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->mine('confirm')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-pending');

        $this->assertSame(['interview.confirmed', 'interview.panel_invited'], $this->notificationTypes($this->recruiterId));
        $this->assertSame([], $this->notificationTypes($this->adminId));
    }

    public function test_proposing_another_time_is_limited_and_the_team_accepts_it(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        $proposed = $this->inTheFuture(6);

        $this->mine('propose', ['starts_at' => $proposed])->assertOk()->assertJsonPath('data.proposals_left', 1)->assertJsonPath('data.proposed_starts_at', $this->utc($proposed));
        $this->mine('propose', ['starts_at' => $this->inTheFuture(7)])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:proposal-pending');
        $this->mine('confirm')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:proposal-pending');

        $this->team('accept-proposal')->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.starts_at', $this->utc($proposed))->assertJsonPath('data.proposed_starts_at', null);
        // The accepted time is a new time: the interviewer is invited again.
        $this->assertSame(['interview.panel_invited', 'interview.panel_invited', 'interview.proposed'], $this->notificationTypes($this->recruiterId));
        $this->assertContains('interview.proposal_accepted', $this->notificationTypes($this->seekerId));
    }

    public function test_the_second_proposal_is_the_last(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        DB::table('recruitment_interviews')->update(['proposals_used' => 2]);

        $this->mine('propose', ['starts_at' => $this->inTheFuture(6)])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:proposal-limit');
        $this->mine('propose', ['starts_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z')])->assertStatus(409);
        $this->mine('propose', ['starts_at' => 'soon'])->assertStatus(422);
        $this->mine('decline')->assertOk()->assertJsonPath('data.status', 'declined');
        $this->assertSame(['interview.declined', 'interview.panel_invited'], $this->notificationTypes($this->recruiterId));
        $this->assertSame(0, DB::table('recruitment_interviews')->whereNotNull('active_key')->count());
    }

    public function test_a_declined_interview_can_be_scheduled_again(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        $this->mine('decline')->assertOk();

        $this->mine('', method: 'GET')->assertJsonPath('data', null);
        $this->schedule()->assertCreated();
        $this->mine('confirm')->assertOk();
    }

    public function test_nothing_to_answer_without_an_active_interview(): void
    {
        $this->mine('confirm')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-active-interview');
        $this->team('cancel')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-active-interview');
    }

    public function test_only_the_applicant_answers_and_only_their_own_application(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();

        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", token: $this->recruiterToken)->assertStatus(403);
        $other = $this->tokenFor($this->seeker('lain@example.test'));
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", token: $other)->assertStatus(404);
        $this->api('GET', "/me/applications/{$this->applicationId}/interview", token: $other)->assertStatus(404);
    }

    // ----- the team cancels and completes -----

    public function test_the_team_cancels_and_the_job_seeker_is_told_by_email_too(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();

        $this->team('cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->team('cancel')->assertStatus(409);
        $this->assertContains('interview.cancelled', $this->notificationTypes($this->seekerId));
    }

    public function test_an_interview_is_completed_only_after_it_was_confirmed(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();

        $this->team('complete')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-confirmed');
        $this->mine('confirm')->assertOk();
        $this->team('complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(0, DB::table('recruitment_interviews')->whereNotNull('active_key')->count());
    }

    public function test_rejecting_or_withdrawing_cancels_the_active_interview(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();

        $this->assertSame('cancelled', DB::table('recruitment_interviews')->value('status'));
        $this->assertContains('interview.cancelled', $this->notificationTypes($this->seekerId));

        $second = (int) $this->apply($this->liveJob())->assertCreated()->json('data.id');
        $this->assertGreaterThan(0, $second);
    }

    public function test_withdrawing_cancels_the_active_interview(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", token: $this->seekerToken)->assertNoContent();

        $this->assertSame('cancelled', DB::table('recruitment_interviews')->value('status'));
    }

    // ----- emails -----

    public function test_the_job_seeker_is_emailed_about_the_schedule_and_follows_the_preference(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['meeting_url' => 'https://meet.example.test/secret-room']))->assertOk();

        $emails = $this->sentEmails();
        $this->assertCount(2, array_filter($emails, fn ($email): bool => $email->getTo()[0]->getAddress() === 'siti@example.test'));
        $scheduled = collect($emails)->first(fn ($email): bool => str_contains($email->getSubject() ?? '', 'Interview scheduled'));
        $this->assertNotNull($scheduled);
        $this->assertStringContainsString('https://meet.example.test/secret-room', (string) $scheduled->getTextBody());
        $this->assertStringContainsString('WIB', (string) $scheduled->getTextBody());

        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->seekerToken)->assertNoContent();
        $toSeeker = fn (): int => count(array_filter($this->sentEmails(), fn ($email): bool => $email->getTo()[0]->getAddress() === 'siti@example.test'));
        $before = $toSeeker();
        $this->schedule(['starts_at' => $this->inTheFuture(5)])->assertOk();
        $this->assertSame($before, $toSeeker());
        $this->assertContains('interview.rescheduled', $this->notificationTypes($this->seekerId));
    }

    /** @return list<Email> */
    private function sentEmails(): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();

        return array_values(array_map(fn ($sent) => $sent->getOriginalMessage(), $transport->messages()->all()));
    }

    // ----- reminders -----

    public function test_a_confirmed_interview_within_a_day_is_reminded_once_to_both_sides(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['starts_at' => $this->inTheFuture(3)]))->assertOk();
        $this->mine('confirm')->assertOk();
        $reminders = $this->app->make(SendInterviewReminders::class);

        $this->assertSame(0, $reminders->execute());

        Carbon::setTestNow(now()->addDays(2)->addHours(2));
        $this->assertSame(1, $reminders->execute());
        $this->assertSame(0, $reminders->execute());

        $this->assertContains('interview.reminder', $this->notificationTypes($this->seekerId));
        $this->assertContains('interview.reminder', $this->notificationTypes($this->recruiterId));
        $this->assertNotNull(DB::table('recruitment_interviews')->value('reminded_at'));
    }

    public function test_a_pending_interview_is_not_reminded(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['starts_at' => $this->inTheFuture(1)]))->assertOk();

        $this->assertSame(0, $this->app->make(SendInterviewReminders::class)->execute());
    }

    public function test_the_reminder_command_runs(): void
    {
        $this->artisan('recruitment:send-interview-reminders')->expectsOutput('Reminded 0 interviews.')->assertSuccessful();
    }

    // ----- video interviews -----

    private function enableVideo(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VIDEO_ENABLED, true, null);
    }

    private function videoSchedule(array $overrides = []): array
    {
        return $this->interviewSchedule([...['format' => 'video', 'meeting_url' => null], ...$overrides]);
    }

    public function test_a_video_interview_is_refused_until_an_admin_switches_it_on(): void
    {
        $this->toInterviewStage($this->videoSchedule())->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:video-not-enabled');
        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'));

        $this->enableVideo();
        $this->toInterviewStage($this->videoSchedule())->assertOk();

        $row = DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->first();
        $this->assertSame(['video', null, null, 0], [$row->format, $row->location, $row->meeting_url, (int) $row->recorded]);
    }

    public function test_an_admin_can_switch_video_on_for_one_company_only(): void
    {
        $this->toInterviewStage($this->videoSchedule())->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:video-not-enabled');

        $platformAdmin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $this->app->make(SettingsStore::class)->setCompanyOverride(InterviewSettings::VIDEO_ENABLED, $this->companyId, true, (int) $platformAdmin->id);
        $this->toInterviewStage($this->videoSchedule())->assertOk();
    }

    public function test_only_a_video_interview_can_be_marked_recorded(): void
    {
        $this->enableVideo();
        $this->toInterviewStage($this->interviewSchedule(['recorded' => true]))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview')->assertJsonPath('errors.recorded.0', 'Only an interview held in the platform can be recorded.');
        $this->toInterviewStage($this->videoSchedule(['recorded' => true]))->assertOk();

        $this->team('cancel')->assertOk();
        $this->schedule(['recorded' => 'maybe'])->assertStatus(422)->assertJsonValidationErrors(['recorded']);
    }

    public function test_a_recorded_interview_needs_the_answer_to_the_recording_when_confirming(): void
    {
        $this->enableVideo();
        $this->toInterviewStage($this->videoSchedule(['recorded' => true]))->assertOk();

        $this->mine('', method: 'GET')->assertOk()->assertJsonPath('data.format', 'video')->assertJsonPath('data.recorded', true)->assertJsonPath('data.recording_consent', null);
        $this->mine('confirm')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:recording-consent-required');
        $this->mine('confirm', ['recording_consent' => 'withdrawn'])->assertStatus(422)->assertJsonValidationErrors(['recording_consent']);
        $this->assertSame('pending', DB::table('recruitment_interviews')->value('status'));

        $this->mine('confirm', ['recording_consent' => 'granted'])->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.recording_consent', 'granted');
        $this->assertNotNull(DB::table('recruitment_interviews')->value('recording_consent_at'));
    }

    public function test_refusing_the_recording_keeps_the_interview(): void
    {
        $this->enableVideo();
        $this->toInterviewStage($this->videoSchedule(['recorded' => true]))->assertOk();

        $this->mine('confirm', ['recording_consent' => 'refused'])->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.recording_consent', 'refused');
        $this->mine('recording-consent/withdraw')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-recording-consent');
    }

    public function test_an_interview_that_is_not_recorded_ignores_the_answer(): void
    {
        $this->enableVideo();
        $this->toInterviewStage($this->videoSchedule())->assertOk();

        $this->mine('confirm', ['recording_consent' => 'granted'])->assertOk()->assertJsonPath('data.recorded', false)->assertJsonPath('data.recording_consent', null);
        $this->mine('recording-consent/withdraw')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-recording-consent');
    }

    public function test_a_proposal_for_a_recorded_interview_carries_the_answer(): void
    {
        $this->enableVideo();
        $this->toInterviewStage($this->videoSchedule(['recorded' => true]))->assertOk();

        $this->mine('propose', ['starts_at' => $this->inTheFuture(6)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:recording-consent-required');
        $this->mine('propose', ['starts_at' => $this->inTheFuture(6), 'recording_consent' => 'granted'])->assertOk()->assertJsonPath('data.recording_consent', 'granted');

        $this->team('accept-proposal')->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.recording_consent', 'granted');
    }

    public function test_the_job_seeker_withdraws_a_given_consent_once(): void
    {
        $this->enableVideo();
        $this->toInterviewStage($this->videoSchedule(['recorded' => true]))->assertOk();
        $this->mine('confirm', ['recording_consent' => 'granted'])->assertOk();

        $this->mine('recording-consent/withdraw')->assertOk()->assertJsonPath('data.recording_consent', 'withdrawn')->assertJsonPath('data.status', 'confirmed');
        $this->mine('recording-consent/withdraw')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-recording-consent');
        $this->assertSame(['interview.confirmed', 'interview.panel_invited', 'interview.recording_withdrawn'], $this->notificationTypes($this->recruiterId));
        $this->assertSame([], $this->notificationTypes($this->adminId));
    }

    public function test_withdrawing_needs_an_active_interview_and_the_applicant(): void
    {
        $this->mine('recording-consent/withdraw')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-active-interview');
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/recording-consent/withdraw", token: $this->recruiterToken)->assertForbidden();
    }

    public function test_a_new_video_time_keeps_the_flag_unless_the_request_says_otherwise(): void
    {
        $this->enableVideo();
        $this->toInterviewStage($this->videoSchedule(['recorded' => true]))->assertOk();
        $this->mine('confirm', ['recording_consent' => 'granted'])->assertOk();

        $this->schedule($this->videoSchedule(['starts_at' => $this->inTheFuture(5)]))->assertOk()->assertJsonPath('data.recorded', true)->assertJsonPath('data.recording_consent', null)->assertJsonPath('data.status', 'pending');
        $this->schedule($this->videoSchedule(['starts_at' => $this->inTheFuture(6), 'recorded' => false]))->assertOk()->assertJsonPath('data.recorded', false);
        $this->schedule(['starts_at' => $this->inTheFuture(7)])->assertOk()->assertJsonPath('data.format', 'online')->assertJsonPath('data.recorded', false);
    }

    public function test_a_bulk_move_can_schedule_recorded_video_interviews(): void
    {
        $body = ['stage_id' => $this->stage('interview'), 'application_ids' => [$this->applicationId], 'interview' => $this->videoSchedule(['recorded' => true])];

        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", $body, $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:video-not-enabled');

        $this->enableVideo();
        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", $body, $this->recruiterToken)->assertSuccessful();

        $row = DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->first();
        $this->assertSame(['video', 1], [$row->format, (int) $row->recorded]);
    }

    public function test_the_email_for_a_video_interview_says_where_it_is_held_and_whether_it_is_recorded(): void
    {
        $this->enableVideo();
        $this->toInterviewStage($this->videoSchedule(['recorded' => true]))->assertOk();

        $scheduled = collect($this->sentEmails())->first(fn ($email): bool => str_contains($email->getSubject() ?? '', 'Interview scheduled'));
        $this->assertNotNull($scheduled);
        $body = (string) $scheduled->getTextBody();
        $this->assertStringContainsString('Video in the platform', $body);
        $this->assertStringContainsString('open your application to join', $body);
        $this->assertStringContainsString('Recorded: Yes, only if you agree', $body);
    }

    // ----- erasure -----

    public function test_erasing_the_applicant_deletes_their_interviews(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();

        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        $this->assertSame(0, DB::table('recruitment_interviews')->count());
    }

    private function utc(string $iso): string
    {
        return Carbon::parse($iso)->utc()->format(DATE_ATOM);
    }
}
