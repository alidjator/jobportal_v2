<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interviewers.md` section 1: being on an interview's panel opens the interview material without an assignment to the job, and the list of one's interviews. */
final class InterviewerPanelAccessTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    private int $interview = 0;

    private int $interviewerId;

    private string $interviewerToken;

    private int $strangerId;

    private string $strangerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');

        ['id' => $this->interviewerId, 'token' => $this->interviewerToken] = $this->memberWith(['interviews.participate'], 'interviewer@example.test');
        ['id' => $this->strangerId, 'token' => $this->strangerToken] = $this->memberWith(['interviews.participate'], 'stranger@example.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** Schedules a confirmed interview with the interviewer on the panel (or as the main interviewer) and moves the clock into it. */
    private function openInterview(bool $asMain = false): void
    {
        $kit = (int) $this->api('POST', '/company/interview-kits', ['name' => 'Backend round', 'questions' => [
            ['text' => 'Explain a database transaction.', 'weight' => 5],
        ]], $this->recruiterToken)->assertCreated()->json('data.id');

        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        $schedule = $this->interviewSchedule(['kit_id' => $kit, ...($asMain ? ['interviewer_id' => $this->interviewerId] : ['participant_ids' => [$this->interviewerId]])]);
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $schedule], $this->recruiterToken)->assertOk();
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();
        $this->interview = (int) DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->value('id');
    }

    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->interviewerToken = $this->tokenFor($this->interviewerId);
        $this->strangerToken = $this->tokenFor($this->strangerId);
        $this->adminToken = $this->tokenFor($this->adminId);
    }

    private function mine(array $query = [], ?string $token = null): TestResponse
    {
        return $this->api('GET', '/company/interviews?'.http_build_query($query), [], $token ?? $this->interviewerToken);
    }

    // ----- the panel opens the application -----

    public function test_a_panel_member_opens_the_application_without_the_job_and_sees_no_ratings(): void
    {
        $this->openInterview();
        $this->api('PUT', "/company/applications/{$this->applicationId}/rating", ['stars' => 4], $this->recruiterToken)->assertOk();

        $this->api('GET', "/company/applications/{$this->applicationId}", [], $this->interviewerToken)->assertOk()
            ->assertJsonPath('data.id', $this->applicationId)
            ->assertJsonPath('data.rating.count', 0)
            ->assertJsonPath('data.rating.average', null)
            ->assertJsonPath('data.other_applications', []);
        $this->api('GET', "/company/applications/{$this->applicationId}/interviews", [], $this->interviewerToken)->assertOk()->assertJsonCount(1, 'data');
        $this->api('GET', "/company/assessment/applications/{$this->applicationId}/invitations", [], $this->interviewerToken)->assertOk()->assertJsonCount(0, 'data');
        $this->assertNull(DB::table('recruitment_applications')->where('id', $this->applicationId)->value('viewed_at'));

        // The recruiter keeps the full view.
        $this->api('GET', "/company/applications/{$this->applicationId}", [], $this->recruiterToken)->assertOk()->assertJsonPath('data.rating.count', 1);
    }

    public function test_the_panel_does_not_open_notes_ratings_stage_moves_or_the_schedule(): void
    {
        $this->openInterview();
        $panel = $this->interviewerToken;

        $this->api('GET', "/company/applications/{$this->applicationId}/notes", [], $panel)->assertForbidden();
        $this->api('PUT', "/company/applications/{$this->applicationId}/rating", ['stars' => 5], $panel)->assertForbidden();
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => 1], $panel)->assertForbidden();
        $this->api('POST', "/company/applications/{$this->applicationId}/interview/cancel", [], $panel)->assertForbidden();
        $this->api('PUT', "/company/interviews/{$this->interview}/participants", ['participant_ids' => []], $panel)->assertForbidden();
        $this->assertSame(1, DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->where('status', 'confirmed')->count());
    }

    public function test_a_member_who_is_not_on_the_panel_gets_the_ordinary_refusal(): void
    {
        $this->openInterview();

        // Without a seat on the panel the member has no reading right here: the answer is the one of a member who may not read applicants.
        foreach (["/company/applications/{$this->applicationId}", "/company/applications/{$this->applicationId}/interviews", "/company/interviews/{$this->interview}/questions", "/company/interviews/{$this->interview}/recordings"] as $uri) {
            $this->api('GET', $uri, [], $this->strangerToken)->assertForbidden()->assertJsonPath('permission', 'applications.view');
        }
    }

    public function test_the_panel_permission_is_needed_and_removal_closes_the_door(): void
    {
        $this->openInterview();
        $viewer = $this->memberWith(['applications.view'], 'viewer@example.test')['token'];

        $this->api('GET', "/company/applications/{$this->applicationId}", [], $viewer)->assertNotFound();

        // Taking the member off the panel closes the application again; the permission alone is not enough.
        $this->api('PUT', "/company/interviews/{$this->interview}/participants", ['participant_ids' => []], $this->recruiterToken)->assertOk();
        $this->api('GET', "/company/applications/{$this->applicationId}", [], $this->interviewerToken)->assertForbidden();
    }

    public function test_the_panel_reads_the_questions_and_the_recordings_of_the_interviews_they_sat_on(): void
    {
        $this->openInterview();

        $this->api('GET', "/company/interviews/{$this->interview}/questions", [], $this->interviewerToken)->assertOk()->assertJsonCount(1, 'data.questions');
        $this->api('GET', "/company/interviews/{$this->interview}/recordings", [], $this->interviewerToken)->assertOk()->assertJsonCount(0, 'data');
        $this->api('GET', "/company/interviews/{$this->interview}/calendar.ics", [], $this->interviewerToken)->assertOk();
    }

    public function test_the_main_interviewer_may_edit_the_questions_and_a_plain_panel_member_may_not(): void
    {
        $this->openInterview();
        $questions = ['questions' => [['text' => 'A new question', 'weight' => 2]]];

        $this->api('PUT', "/company/interviews/{$this->interview}/questions", $questions, $this->interviewerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:interview-edit-denied');

        $this->api('PUT', "/company/interviews/{$this->interview}/participants", ['participant_ids' => []], $this->recruiterToken)->assertOk();
        DB::table('recruitment_interviews')->where('id', $this->interview)->update(['interviewer_id' => $this->interviewerId]);
        $this->api('PUT', "/company/interviews/{$this->interview}/questions", $questions, $this->interviewerToken)->assertOk();
    }

    // ----- the scorecard -----

    public function test_a_panel_member_without_the_job_fills_and_sends_their_scorecard(): void
    {
        $this->openInterview(asMain: true);
        $this->moveClockTo('2026-10-08 09:30:00');
        $questionId = (int) DB::table('recruitment_interview_questions')->where('interview_id', $this->interview)->value('id');

        $this->api('PUT', "/company/interviews/{$this->interview}/scorecard", ['recommendation' => 'yes', 'note' => null, 'answers' => [['question_id' => $questionId, 'score' => 4]]], $this->interviewerToken)->assertOk();
        $this->api('POST', "/company/interviews/{$this->interview}/scorecard/submit", [], $this->interviewerToken)->assertOk();
        $this->api('GET', "/company/interviews/{$this->interview}/scorecards", [], $this->interviewerToken)->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, DB::table('recruitment_interview_scorecards')->where('member_id', $this->interviewerId)->where('status', 'submitted')->count());
    }

    // ----- one interview -----

    public function test_a_panel_member_opens_one_interview_by_its_id_with_the_job_the_candidate_and_the_panel(): void
    {
        $this->openInterview();

        $this->api('GET', "/company/interviews/{$this->interview}", [], $this->interviewerToken)->assertOk()
            ->assertJsonPath('data.id', $this->interview)
            ->assertJsonPath('data.application_id', $this->applicationId)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.job.id', $this->job)
            ->assertJsonPath('data.job.title', 'Backend Developer')
            ->assertJsonPath('data.candidate_name', 'Siti Rahma')
            ->assertJsonPath('data.kit.name', 'Backend round')
            ->assertJsonCount(1, 'data.participants')
            ->assertJsonPath('data.participants.0.id', $this->interviewerId)
            ->assertJsonPath('data.participants.0.rsvp.answer', 'pending');
    }

    public function test_the_team_of_the_job_opens_the_interview_and_a_stranger_does_not(): void
    {
        $this->openInterview();

        $this->api('GET', "/company/interviews/{$this->interview}", [], $this->recruiterToken)->assertOk()->assertJsonPath('data.job.title', 'Backend Developer');
        $this->api('GET', "/company/interviews/{$this->interview}", [], $this->strangerToken)->assertForbidden()->assertJsonPath('permission', 'applications.view');
        $this->api('GET', '/company/interviews/999999', [], $this->recruiterToken)->assertNotFound();

        // The guard keeps the member of the last call in this process: forget it before calling without a token.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson("/api/v1/company/interviews/{$this->interview}")->assertUnauthorized();
    }

    // ----- my interviews -----

    public function test_my_interviews_lists_what_the_member_sits_on(): void
    {
        $this->openInterview();

        $row = $this->mine()->assertOk()->assertJsonCount(1, 'data')->json('data.0');
        $this->assertSame([$this->interview, $this->applicationId, 'Backend Developer', 'Siti Rahma', 'panel', 'not_open'], [
            $row['id'], $row['application_id'], $row['job']['title'], $row['candidate_name'], $row['role'], $row['scorecard'],
        ]);
        $this->assertSame('confirmed', $row['status']);

        $this->mine([], $this->strangerToken)->assertOk()->assertJsonCount(0, 'data');
        $this->mine(['filter' => ['when' => 'past']])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_my_interviews_follows_the_clock_and_the_scorecard_state(): void
    {
        $this->openInterview(asMain: true);

        $this->moveClockTo('2026-10-08 09:30:00');
        $this->mine(['filter' => ['my_scorecard' => 'pending']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.role', 'main')->assertJsonPath('data.0.scorecard', 'pending');
        $this->mine()->assertOk()->assertJsonCount(1, 'data');

        $questionId = (int) DB::table('recruitment_interview_questions')->where('interview_id', $this->interview)->value('id');
        $this->api('PUT', "/company/interviews/{$this->interview}/scorecard", ['recommendation' => 'yes', 'note' => null, 'answers' => [['question_id' => $questionId, 'score' => 4]]], $this->interviewerToken)->assertOk();
        $this->api('POST', "/company/interviews/{$this->interview}/scorecard/submit", [], $this->interviewerToken)->assertOk();
        $this->mine(['filter' => ['my_scorecard' => 'pending']])->assertOk()->assertJsonCount(0, 'data');

        $this->moveClockTo('2026-10-09 12:00:00');
        $this->mine()->assertOk()->assertJsonCount(0, 'data');
        $this->mine(['filter' => ['when' => 'past']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.scorecard', 'sent');
    }

    public function test_every_interview_of_the_company_needs_the_manage_permission(): void
    {
        $this->openInterview();

        $this->mine(['filter' => ['mine' => '0']])->assertForbidden();
        $this->mine(['filter' => ['mine' => '0']], $this->adminToken)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.role', null)->assertJsonPath('data.0.scorecard', null);
        $this->mine([], $this->recruiterToken)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.role', 'main');
    }

    public function test_my_interviews_refuses_unknown_filters_and_is_paginated(): void
    {
        $this->mine(['filter' => ['when' => 'sometime']])->assertStatus(422);
        $this->mine(['filter' => ['other' => '1']])->assertStatus(422);
        $this->mine(['cursor' => 'not-a-cursor'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-cursor');
        $this->api('GET', '/company/interviews', [], $this->seekerToken)->assertForbidden();
    }

    // ----- choosing the panel -----

    public function test_the_panel_is_chosen_by_permission_and_not_by_assignment(): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        $viewer = $this->memberWith(['applications.view'], 'viewer@example.test')['id'];

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $this->interviewSchedule(['participant_ids' => [$viewer]])], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interviewer');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $this->interviewSchedule(['interviewer_id' => $this->interviewerId])], $this->recruiterToken)->assertOk();
    }
}
