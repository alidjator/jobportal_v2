<?php

namespace Tests\Feature\Assessment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/** Phase 6, part 4: a test is sent when an application moves to an assessment stage (one by one or in bulk), kept, replaced, cancelled, reminded and expired. */
final class InvitationTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $job;

    private int $applicationId;

    private int $testId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $this->testId = $this->publishedTest();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    /** A published skill test of the company: one section of 10 minutes with one question. */
    private function publishedTest(string $title = 'Logic and numbers', ?array $question = null): int
    {
        $id = $this->newTest('company', ['title' => $title]);
        $this->sectionWithQuestion('company', $id, $question);
        $this->company('POST', "tests/{$id}/publish")->assertOk();

        return $id;
    }

    /** @return array<string, mixed> */
    private function invitation(array $overrides = []): array
    {
        return ['test_id' => $this->testId, 'due_at' => $this->at(now()->addDays(3)), ...$overrides];
    }

    private function at(Carbon $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function moveToAssessment(?array $assessment = null, ?int $id = null, ?int $stage = null, ?string $token = null): TestResponse
    {
        $body = ['stage_id' => $stage ?? $this->stage('assessment')];

        if ($assessment !== null) {
            $body['assessment'] = $assessment;
        }

        return $this->api('POST', '/company/applications/'.($id ?? $this->applicationId).'/move', $body, $token ?? $this->recruiterToken);
    }

    private function send(array $overrides = [], ?string $token = null, ?int $id = null): TestResponse
    {
        return $this->company('PUT', 'applications/'.($id ?? $this->applicationId).'/invitation', $this->invitation($overrides), $token ?? $this->recruiterToken);
    }

    private function mine(string $uri = '', ?string $token = null): TestResponse
    {
        return $this->api('GET', '/me/assessment/invitations'.$uri, [], $token ?? $this->seekerToken);
    }

    /** @return list<string> */
    private function invitationStatuses(): array
    {
        return DB::table('assessment_invitations')->where('application_id', $this->applicationId)->orderBy('id')->pluck('status')->all();
    }

    /** @return list<string> */
    private function notificationTypes(int $userId): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', 'assessment.%')->pluck('type')->all();
        sort($types);

        return $types;
    }

    /** @return list<Email> the emails the array mailer received so far */
    private function emails(): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();

        return array_values(array_map(fn ($sent): Email => $sent->getOriginalMessage(), $transport->messages()->all()));
    }

    private function stageType(): string
    {
        return (string) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type');
    }

    // ----- moving to an assessment stage -----

    public function test_a_move_to_an_assessment_stage_needs_a_test_and_sends_it_with_the_move(): void
    {
        $this->moveToAssessment()->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:assessment-required');
        $this->assertSame('applied', $this->stageType());

        $this->moveToAssessment($this->invitation(['assessor_ids' => [$this->adminId]]))->assertNoContent();

        $this->assertSame('assessment', $this->stageType());
        $row = DB::table('assessment_invitations')->first();
        $this->assertSame(['invited', $this->testId, $this->seekerId, $this->recruiterId, $this->job, $this->companyId], [$row->status, (int) $row->test_id, (int) $row->applicant_user_id, (int) $row->invited_by_user_id, (int) $row->job_id, (int) $row->company_id]);
        $this->assertSame([$this->adminId], DB::table('assessment_invitation_assessors')->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(now()->format('Y-m-d H:i:s'), $row->starts_at);
        $this->assertSame(['assessment.invited'], $this->notificationTypes($this->seekerId));
        $this->assertSame([], $this->notificationTypes($this->recruiterId));
    }

    public function test_the_invitation_email_follows_the_preference_and_names_the_test(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();

        $emails = array_values(array_filter($this->emails(), fn (Email $email): bool => str_contains((string) $email->getSubject(), 'A test for your application')));
        $this->assertCount(1, $emails);
        $this->assertSame('siti@example.test', $emails[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('Logic and numbers', (string) $emails[0]->getTextBody());
        $this->assertStringContainsString('2026-10-08 16:00 WIB', (string) $emails[0]->getTextBody());

        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->seekerToken)->assertNoContent();
        $this->send(['due_at' => $this->at(now()->addDays(4))])->assertOk();

        $this->assertCount(1, array_filter($this->emails(), fn (Email $email): bool => str_contains((string) $email->getSubject(), 'A test for your application')));
        $this->assertSame(['assessment.invited', 'assessment.invited'], $this->notificationTypes($this->seekerId));
    }

    public function test_an_invalid_request_stops_the_move(): void
    {
        $draft = $this->newTest('company', ['title' => 'Draft']);
        $other = (int) $this->company('POST', 'tests', ['title' => 'Theirs', 'measured_capabilities' => ['Logic']], $this->otherAdminToken)->assertCreated()->json('data.id');
        $otherSection = (int) $this->company('POST', "tests/{$other}/sections", $this->section(), $this->otherAdminToken)->assertCreated()->json('data.id');
        $this->company('POST', "sections/{$otherSection}/questions", $this->choice(), $this->otherAdminToken)->assertCreated();
        $this->company('POST', "tests/{$other}/publish", [], $this->otherAdminToken)->assertOk();

        foreach ([
            [['test_id' => $draft], 'invalid-assessment-test'],
            [['test_id' => $other], 'invalid-assessment-test'],
            [['test_id' => 999_999], 'invalid-assessment-test'],
            [['starts_at' => $this->at(now()->subHour())], 'invalid-assessment-schedule'],
            [['due_at' => $this->at(now()->addMinutes(30))], 'invalid-assessment-schedule'],
            [['due_at' => $this->at(now()->addDays(31))], 'invalid-assessment-schedule'],
            [['starts_at' => $this->at(now()->addDays(2)), 'due_at' => $this->at(now()->addDays(2)->addMinutes(5))], 'invalid-assessment-schedule'],
            [['assessor_ids' => [$this->secondRecruiterId]], 'invalid-assessor'],
            [['assessor_ids' => [$this->otherAdminId]], 'invalid-assessor'],
        ] as [$overrides, $code]) {
            $this->moveToAssessment($this->invitation($overrides))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:'.$code);
        }

        $this->moveToAssessment($this->invitation(['due_at' => 'tomorrow']))->assertStatus(422);
        $this->moveToAssessment(['test_id' => $this->testId])->assertStatus(422);

        $this->assertSame('applied', $this->stageType());
        $this->assertSame(0, DB::table('assessment_invitations')->count());
    }

    public function test_the_error_names_the_field(): void
    {
        $this->moveToAssessment($this->invitation(['due_at' => $this->at(now()->addMinutes(30))]))->assertStatus(422)->assertJsonStructure(['errors' => ['due_at']]);
        $this->moveToAssessment($this->invitation(['test_id' => 999_999]))->assertStatus(422)->assertJsonStructure(['errors' => ['test_id']]);
    }

    public function test_the_window_follows_the_company_limits(): void
    {
        $this->overrideSetting('assessment.min_due_hours', 6);
        $this->overrideSetting('assessment.max_due_days', 2);

        $this->moveToAssessment($this->invitation(['due_at' => $this->at(now()->addHours(5))]))->assertStatus(422)->assertJsonPath('errors.due_at.0', 'The due time must be at least 6 hours from now.');
        $this->moveToAssessment($this->invitation(['due_at' => $this->at(now()->addDays(3))]))->assertStatus(422)->assertJsonPath('errors.due_at.0', 'The due time can be at most 2 days from now.');
        $this->moveToAssessment($this->invitation(['due_at' => $this->at(now()->addDay())]))->assertNoContent();
    }

    public function test_a_test_with_long_text_answers_needs_assessors(): void
    {
        $essay = $this->publishedTest('Essay', ['type' => 'long_text', 'text' => 'Describe your last project.', 'points' => 10]);

        $this->moveToAssessment($this->invitation(['test_id' => $essay]))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-assessor');
        $this->moveToAssessment($this->invitation(['test_id' => $essay, 'assessor_ids' => [$this->adminId, $this->recruiterId]]))->assertNoContent();
    }

    public function test_at_most_five_assessors_are_allowed(): void
    {
        $ids = [$this->adminId, $this->recruiterId, $this->secondRecruiterId, 999_991, 999_992, 999_993];

        $this->moveToAssessment($this->invitation(['assessor_ids' => $ids]))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-assessor');
    }

    public function test_a_test_is_refused_for_another_stage(): void
    {
        $this->moveToAssessment($this->invitation(), stage: $this->stage('screening'))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-assessment-input');
        $this->assertSame('applied', $this->stageType());
    }

    public function test_the_library_is_open_to_every_company_and_a_battery_needs_a_grant_and_the_setting(): void
    {
        $library = $this->newTest('library', ['title' => 'Library logic']);
        $this->sectionWithQuestion('library', $library);
        $this->library('POST', "tests/{$library}/publish")->assertOk();

        $battery = $this->newTest('provider', ['title' => 'Personality']);
        $this->sectionWithQuestion('provider', $battery, [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Always', 'score_value' => 3]],
        ]);
        $this->provider('PUT', "tests/{$battery}/norms", ['norms' => [['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 85.5, 'band' => 'Low']]])->assertOk();
        $this->provider('PUT', "tests/{$battery}/report-template", ['body' => 'Dear {{candidate_name}}'])->assertOk();
        $this->provider('POST', "tests/{$battery}/publish")->assertOk();

        $this->moveToAssessment($this->invitation(['test_id' => $battery]))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-assessment-test');

        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $battery])->assertNoContent();
        $this->moveToAssessment($this->invitation(['test_id' => $battery]))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-assessment-test');

        $this->overrideSetting('assessment.psychological_enabled', true);
        $this->moveToAssessment($this->invitation(['test_id' => $battery, 'assessor_ids' => [$this->adminId]]))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-assessor');
        $this->moveToAssessment($this->invitation(['test_id' => $battery]))->assertNoContent();

        $this->send(['test_id' => $library])->assertOk();
        $this->assertSame(['cancelled', 'invited'], $this->invitationStatuses());
    }

    // ----- keeping and leaving the stage -----

    public function test_moving_between_assessment_stages_keeps_the_active_test_and_needs_no_new_one(): void
    {
        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'Second test', 'type' => 'assessment'], $this->adminToken)->assertCreated()->json('data.id');
        $this->moveToAssessment($this->invitation())->assertNoContent();

        $this->moveToAssessment(stage: $extra)->assertNoContent();

        $this->assertSame(['invited'], $this->invitationStatuses());
    }

    public function test_a_move_between_assessment_stages_without_an_active_test_needs_one(): void
    {
        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'Second test', 'type' => 'assessment'], $this->adminToken)->assertCreated()->json('data.id');
        $this->moveToAssessment($this->invitation())->assertNoContent();
        $this->company('POST', 'invitations/'.DB::table('assessment_invitations')->value('id').'/cancel')->assertOk();

        $this->moveToAssessment(stage: $extra)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:assessment-required');
    }

    public function test_moving_out_of_the_stage_cancels_the_active_test_and_tells_the_candidate(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertNoContent();

        $this->assertSame(['cancelled'], $this->invitationStatuses());
        $this->assertNotNull(DB::table('assessment_invitations')->value('closed_at'));
        $this->assertSame(['assessment.cancelled', 'assessment.invited'], $this->notificationTypes($this->seekerId));
    }

    public function test_a_rejection_cancels_the_active_test(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();

        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'failed_screening'], $this->recruiterToken)->assertNoContent();

        $this->assertSame(['cancelled'], $this->invitationStatuses());
    }

    public function test_a_withdrawal_cancels_the_active_test(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();

        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", [], $this->seekerToken)->assertNoContent();

        $this->assertSame(['cancelled'], $this->invitationStatuses());
    }

    public function test_a_submitted_test_stays_when_the_application_moves_on(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();
        DB::table('assessment_invitations')->update(['status' => 'submitted']);

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertNoContent();

        $this->assertSame(['submitted'], $this->invitationStatuses());
    }

    // ----- sending and replacing -----

    public function test_a_test_is_sent_and_replaced_only_in_an_assessment_stage(): void
    {
        $this->send()->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:not-in-assessment-stage');

        $this->moveToAssessment($this->invitation())->assertNoContent();
        $this->company('POST', 'invitations/'.DB::table('assessment_invitations')->value('id').'/cancel')->assertOk();

        $this->send(['starts_at' => $this->at(now()->addDay()), 'due_at' => $this->at(now()->addDays(5))])->assertCreated()
            ->assertJsonPath('data.status', 'invited')->assertJsonPath('data.test.title', 'Logic and numbers');

        $replacement = $this->publishedTest('Second test');
        $this->send(['test_id' => $replacement])->assertOk()->assertJsonPath('data.test.title', 'Second test');

        $this->assertSame(['cancelled', 'cancelled', 'invited'], $this->invitationStatuses());
    }

    public function test_a_test_the_candidate_started_cannot_be_replaced(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();
        DB::table('assessment_invitations')->update(['status' => 'started']);

        $this->send()->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:invitation-in-progress');
        $this->moveToAssessment($this->invitation(), stage: $this->stage('assessment'))->assertStatus(409);

        $this->assertSame(['started'], $this->invitationStatuses());
    }

    public function test_a_taken_down_job_sends_nothing(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();
        DB::table('job_jobs')->where('id', $this->job)->update(['status' => 'taken_down']);

        $this->send()->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:job-taken-down');
    }

    public function test_only_members_who_see_the_job_send_tests(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();

        $this->send([], $this->secondRecruiterToken)->assertNotFound();
        $this->send([], $this->otherAdminToken)->assertNotFound();
        $this->send([], $this->seekerToken)->assertForbidden();
        $this->company('GET', "applications/{$this->applicationId}/invitations", [], $this->secondRecruiterToken)->assertNotFound();
    }

    // ----- the team's view and cancelling -----

    public function test_the_team_reads_the_tests_of_an_application_and_cancels_the_active_one(): void
    {
        $this->moveToAssessment($this->invitation(['assessor_ids' => [$this->adminId]]))->assertNoContent();
        $id = (int) DB::table('assessment_invitations')->value('id');

        $this->company('GET', "applications/{$this->applicationId}/invitations", [], $this->adminToken)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.assessors.0.name', 'Andi Admin')->assertJsonPath('data.0.test.kind', 'skill');
        $this->company('GET', "invitations/{$id}")->assertOk()->assertJsonPath('data.status', 'invited');

        $this->company('POST', "invitations/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('assessment', $this->stageType());
        $this->assertSame(['assessment.cancelled', 'assessment.invited'], $this->notificationTypes($this->seekerId));

        $this->company('POST', "invitations/{$id}/cancel")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:invitation-not-active');
        $this->company('POST', "invitations/{$id}/cancel", [], $this->otherAdminToken)->assertNotFound();
        $this->company('GET', "invitations/{$id}", [], $this->secondRecruiterToken)->assertNotFound();
        $this->company('GET', 'invitations/999999')->assertNotFound();
    }

    // ----- expiry and reminders -----

    public function test_an_unstarted_test_expires_at_its_due_time(): void
    {
        $this->moveToAssessment($this->invitation(['due_at' => $this->at(now()->addHours(2))]))->assertNoContent();

        $this->artisan('assessment:expire-invitations')->expectsOutput('Expired 0 invitations.')->assertSuccessful();
        $this->assertSame(['invited'], $this->invitationStatuses());

        Carbon::setTestNow(now()->addHours(3));
        $this->artisan('assessment:expire-invitations')->expectsOutput('Expired 1 invitation.')->assertSuccessful();
        $this->artisan('assessment:expire-invitations')->expectsOutput('Expired 0 invitations.')->assertSuccessful();

        $this->assertSame(['expired'], $this->invitationStatuses());
        $this->assertSame(['assessment.expired', 'assessment.invited'], $this->notificationTypes($this->seekerId));
        $this->assertSame('assessment', $this->stageType());
    }

    public function test_a_started_test_does_not_expire_here(): void
    {
        $this->moveToAssessment($this->invitation(['due_at' => $this->at(now()->addHours(2))]))->assertNoContent();
        DB::table('assessment_invitations')->update(['status' => 'started']);

        Carbon::setTestNow(now()->addHours(3));
        $this->artisan('assessment:expire-invitations')->expectsOutput('Expired 0 invitations.')->assertSuccessful();

        $this->assertSame(['started'], $this->invitationStatuses());
    }

    public function test_the_candidate_is_reminded_once_a_day_before_the_due_time(): void
    {
        $this->moveToAssessment($this->invitation(['due_at' => $this->at(now()->addDays(3))]))->assertNoContent();

        $this->artisan('assessment:send-invitation-reminders')->expectsOutput('Reminded 0 invitations.')->assertSuccessful();

        Carbon::setTestNow(now()->addHours(49));
        $this->artisan('assessment:send-invitation-reminders')->expectsOutput('Reminded 1 invitation.')->assertSuccessful();
        $this->artisan('assessment:send-invitation-reminders')->expectsOutput('Reminded 0 invitations.')->assertSuccessful();

        $this->assertSame(['assessment.invited', 'assessment.reminder'], $this->notificationTypes($this->seekerId));
        $this->assertCount(1, array_filter($this->emails(), fn (Email $email): bool => str_contains((string) $email->getSubject(), 'Reminder: your test')));
    }

    public function test_a_cancelled_or_expired_test_is_not_reminded(): void
    {
        $this->moveToAssessment($this->invitation(['due_at' => $this->at(now()->addHours(10))]))->assertNoContent();
        $this->company('POST', 'invitations/'.DB::table('assessment_invitations')->value('id').'/cancel')->assertOk();

        $this->artisan('assessment:send-invitation-reminders')->expectsOutput('Reminded 0 invitations.')->assertSuccessful();
    }

    // ----- bulk -----

    public function test_a_bulk_move_sends_the_same_test_to_every_application(): void
    {
        $second = $this->secondApplication();

        $response = $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", [
            'application_ids' => [$this->applicationId, $second], 'stage_id' => $this->stage('assessment'), 'assessment' => $this->invitation(),
        ], $this->recruiterToken)->assertOk();

        $this->assertSame([null, null], array_column($response->json('data'), 'code'));
        $this->assertSame(2, DB::table('assessment_invitations')->where('test_id', $this->testId)->count());
        $this->assertSame(['assessment', 'assessment'], DB::table('recruitment_applications')->whereIn('id', [$this->applicationId, $second])->pluck('stage_type')->all());
    }

    public function test_a_bulk_move_checks_the_test_once_before_it_starts(): void
    {
        $second = $this->secondApplication();

        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", [
            'application_ids' => [$this->applicationId, $second], 'stage_id' => $this->stage('assessment'), 'assessment' => $this->invitation(['test_id' => 999_999]),
        ], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-assessment-test');
        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", [
            'application_ids' => [$this->applicationId, $second], 'stage_id' => $this->stage('screening'), 'assessment' => $this->invitation(),
        ], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-assessment-input');

        $this->assertSame(0, DB::table('assessment_invitations')->count());
        $this->assertSame('applied', $this->stageType());
    }

    public function test_a_bulk_move_reports_the_application_whose_test_is_in_progress(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();
        DB::table('assessment_invitations')->update(['status' => 'started']);
        $second = $this->secondApplication();
        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'Second test', 'type' => 'assessment'], $this->adminToken)->assertCreated()->json('data.id');

        $response = $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", [
            'application_ids' => [$this->applicationId, $second], 'stage_id' => $extra, 'assessment' => $this->invitation(),
        ], $this->recruiterToken)->assertOk();

        $this->assertSame(['invitation-in-progress', null], array_column($response->json('data'), 'code'));
    }

    private function secondApplication(): int
    {
        $token = $this->tokenFor($this->seeker('second.candidate@example.test'));
        $this->resumeId = $this->completeProfile($token);

        return (int) $this->apply($this->job, token: $token)->assertCreated()->json('data.id');
    }

    // ----- the candidate's side -----

    public function test_the_candidate_lists_and_reads_their_invitations_without_any_question(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();
        $id = (int) DB::table('assessment_invitations')->value('id');

        $this->mine()->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.job.title', 'Backend Developer')->assertJsonPath('data.0.company.name', 'PT-MAJU')
            ->assertJsonPath('data.0.test.title', 'Logic and numbers')->assertJsonMissingPath('data.0.sections');

        $response = $this->mine("/{$id}")->assertOk()->assertJsonPath('data.status', 'invited')->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.sections.0.duration_seconds', 600)->assertJsonPath('data.total_seconds', 600);
        $this->assertStringNotContainsString('Which is a prime number?', $response->getContent());
        $this->assertStringNotContainsString('is_correct', $response->getContent());

        $this->mine('?filter[status]=expired')->assertOk()->assertJsonCount(0, 'data');
        $this->mine('?filter[status]=invited')->assertOk()->assertJsonCount(1, 'data');
        $this->mine('?filter[status]=bogus')->assertStatus(422);
    }

    public function test_another_candidate_or_a_company_member_does_not_see_the_invitation(): void
    {
        $this->moveToAssessment($this->invitation())->assertNoContent();
        $id = (int) DB::table('assessment_invitations')->value('id');
        $other = $this->tokenFor($this->seeker('other.candidate@example.test'));

        $this->mine("/{$id}", $other)->assertNotFound();
        $this->mine('', $other)->assertOk()->assertJsonCount(0, 'data');
        $this->mine('', $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
        $this->app['auth']->forgetGuards();
        $this->withToken('')->getJson('/api/v1/me/assessment/invitations')->assertUnauthorized();
    }

    // ----- the lock -----

    public function test_a_test_that_was_sent_is_locked_but_can_be_archived_or_cloned(): void
    {
        $sectionId = (int) DB::table('assessment_sections')->where('test_id', $this->testId)->value('id');
        $questionId = (int) DB::table('assessment_questions')->where('section_id', $sectionId)->value('id');
        $this->company('PUT', "tests/{$this->testId}", ['title' => 'Before', 'measured_capabilities' => ['Logic']])->assertOk();

        $this->moveToAssessment($this->invitation())->assertNoContent();

        foreach ([
            ['PUT', "tests/{$this->testId}", ['title' => 'After', 'measured_capabilities' => ['Logic']]],
            ['POST', "tests/{$this->testId}/sections", $this->section('Extra')],
            ['PUT', "sections/{$sectionId}", $this->section('Renamed')],
            ['DELETE', "sections/{$sectionId}", []],
            ['POST', "sections/{$sectionId}/questions", $this->choice('Another?')],
            ['PUT', "questions/{$questionId}", $this->choice('Changed?')],
            ['DELETE', "questions/{$questionId}", []],
        ] as [$method, $uri, $body]) {
            $this->company($method, $uri, $body)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:test-locked');
        }

        $this->assertSame('Before', DB::table('assessment_tests')->where('id', $this->testId)->value('title'));
        $this->assertSame(1, DB::table('assessment_questions')->where('section_id', $sectionId)->count());

        $this->company('POST', "tests/{$this->testId}/clone")->assertCreated();
        $this->company('POST', "tests/{$this->testId}/archive")->assertOk();
        $this->company('POST', "tests/{$this->testId}/restore")->assertOk();
        $this->company('PUT', "tests/{$this->testId}", ['title' => 'After', 'measured_capabilities' => ['Logic']])->assertStatus(409);
    }

    public function test_a_test_nobody_was_sent_stays_editable_after_publishing(): void
    {
        $this->company('PUT', "tests/{$this->testId}", ['title' => 'Still editable', 'measured_capabilities' => ['Logic']])->assertOk();
    }
}
