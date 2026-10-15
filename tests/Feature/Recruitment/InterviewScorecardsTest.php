<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Recruitment\Application\EraseScorecardData;
use Src\Recruitment\Application\SendScorecardReminders;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-7-interview-kits.md`, part 2: scorecards, their visibility, edits, results, the reminder and the erasure. */
final class InterviewScorecardsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    private int $interview = 0;

    /** @var list<int> the ids of the interview's two questions (weights 5 and 3) */
    private array $questionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId, $this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** Schedules the interview with a kit (weights 5 and 3) and the admin on the panel, lets the job seeker confirm it and moves the clock into it. */
    private function openInterview(array $panel = []): void
    {
        $kit = (int) $this->api('POST', '/company/interview-kits', ['name' => 'Backend round', 'questions' => [
            ['text' => 'Explain a database transaction.', 'weight' => 5],
            ['text' => 'Tell us about a hard bug.', 'weight' => 3],
        ]], $this->recruiterToken)->assertCreated()->json('data.id');

        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        $schedule = $this->interviewSchedule(['kit_id' => $kit, 'participant_ids' => $panel === [] ? [$this->adminId] : $panel]);
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $schedule], $this->recruiterToken)->assertNoContent();
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();

        $this->interview = (int) DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->value('id');
        $this->moveClockTo('2026-10-08 09:30:00');

        $this->questionIds = array_map('intval', DB::table('recruitment_interview_questions')->where('interview_id', $this->interview)->orderBy('position')->pluck('id')->all());
    }

    /** The clock moves and the tokens are issued again, since they expire after a day. */
    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->secondRecruiterToken = $this->tokenFor($this->secondRecruiterId);
        $this->otherAdminToken = $this->tokenFor($this->otherAdminId);
    }

    /** @return array<string, mixed> */
    private function card(string $recommendation = 'yes', ?int $first = 5, ?int $second = 2, ?string $note = null): array
    {
        return ['recommendation' => $recommendation, 'note' => $note, 'answers' => [
            ['question_id' => $this->questionIds[0], 'score' => $first],
            ['question_id' => $this->questionIds[1], 'score' => $second],
        ]];
    }

    private function save(array $body, ?string $token = null): TestResponse
    {
        return $this->api('PUT', "/company/interviews/{$this->interview}/scorecard", $body, $token ?? $this->recruiterToken);
    }

    private function send(?string $token = null): TestResponse
    {
        return $this->api('POST', "/company/interviews/{$this->interview}/scorecard/submit", [], $token ?? $this->recruiterToken);
    }

    private function sent(string $recommendation, ?int $first, ?int $second, string $token): void
    {
        $this->save($this->card($recommendation, $first, $second), $token)->assertOk();
        $this->send($token)->assertOk();
    }

    private function sentList(?string $token = null): TestResponse
    {
        return $this->api('GET', "/company/interviews/{$this->interview}/scorecards", [], $token ?? $this->secondRecruiterToken);
    }

    // ----- who may score, and when -----

    public function test_only_the_panel_fills_a_scorecard(): void
    {
        $this->openInterview();

        $this->api('GET', "/company/interviews/{$this->interview}/scorecard", [], $this->secondRecruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-on-panel');
        $this->save($this->card(), $this->secondRecruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-on-panel');
        $this->send($this->secondRecruiterToken)->assertForbidden();

        $this->api('GET', "/company/interviews/{$this->interview}/scorecard", [], $this->adminToken)->assertOk()->assertJsonPath('data.status', null);
        $this->api('GET', "/company/interviews/{$this->interview}/scorecard", [], $this->otherAdminToken)->assertNotFound();
    }

    public function test_scoring_opens_when_a_confirmed_interview_starts(): void
    {
        $this->openInterview();
        $this->moveClockTo('2026-10-08 08:59:00');

        $this->save($this->card())->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:scoring-not-open');

        $this->moveClockTo('2026-10-08 09:00:00');
        $this->save($this->card())->assertOk();

        $this->moveClockTo('2026-10-09 12:00:00');
        $this->api('POST', "/company/applications/{$this->applicationId}/interview/complete", [], $this->recruiterToken)->assertOk();
        $this->save($this->card('no'))->assertOk()->assertJsonPath('data.recommendation', 'no');
    }

    public function test_a_pending_or_cancelled_interview_cannot_be_scored(): void
    {
        $this->openInterview();
        DB::table('recruitment_interviews')->where('id', $this->interview)->update(['status' => 'pending']);
        $this->save($this->card())->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:scoring-not-open');

        DB::table('recruitment_interviews')->where('id', $this->interview)->update(['status' => 'cancelled', 'active_key' => null]);
        $this->save($this->card())->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:scoring-not-open');
    }

    // ----- drafts, sending and the result -----

    public function test_a_draft_is_saved_replaced_and_read_back_with_its_weighted_score(): void
    {
        $this->openInterview();

        $this->save($this->card('yes', 5, 2, '  Strong on databases.  '))->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.note', 'Strong on databases.')
            ->assertJsonPath('data.score', 3.88)
            ->assertJsonPath('data.submitted_at', null);

        $this->save(['recommendation' => null, 'answers' => [['question_id' => $this->questionIds[0], 'score' => 4]]])->assertOk()
            ->assertJsonPath('data.score', 4)
            ->assertJsonCount(1, 'data.answers')
            ->assertJsonPath('data.recommendation', null);

        $this->api('GET', "/company/interviews/{$this->interview}/scorecard", [], $this->recruiterToken)->assertOk()->assertJsonPath('data.score', 4)->assertJsonPath('data.member.name', 'Rina Rekruter');

        $this->save(['answers' => []])->assertOk()->assertJsonPath('data.score', null);
        $this->assertSame(1, DB::table('recruitment_interview_scorecards')->count());
    }

    public function test_an_answer_with_neither_a_score_nor_a_note_is_a_skipped_question(): void
    {
        $this->openInterview();

        $this->save(['answers' => [['question_id' => $this->questionIds[0], 'score' => null, 'note' => '  '], ['question_id' => $this->questionIds[1], 'score' => 3, 'note' => 'Fine.']]])->assertOk()
            ->assertJsonCount(1, 'data.answers')
            ->assertJsonPath('data.answers.0.question_id', $this->questionIds[1])
            ->assertJsonPath('data.score', 3);
    }

    public function test_a_scorecard_that_breaks_a_rule_is_refused_with_the_field(): void
    {
        $this->openInterview();
        $foreign = 987654;

        $this->save(['answers' => [['question_id' => $this->questionIds[0], 'score' => 6]]])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-scorecard')->assertJsonStructure(['errors' => ['answers.0.score']]);
        $this->save(['answers' => [['question_id' => $foreign, 'score' => 3]]])->assertStatus(422)->assertJsonStructure(['errors' => ['answers.0.question_id']]);
        $this->save(['answers' => [['question_id' => $this->questionIds[0], 'score' => 3], ['question_id' => $this->questionIds[0], 'score' => 4]]])->assertStatus(422)->assertJsonStructure(['errors' => ['answers.1.question_id']]);
        $this->save(['note' => str_repeat('n', 2001)])->assertStatus(422)->assertJsonStructure(['errors' => ['note']]);
        $this->save(['answers' => [['question_id' => $this->questionIds[0], 'score' => 3, 'note' => str_repeat('n', 1001)]]])->assertStatus(422)->assertJsonStructure(['errors' => ['answers.0.note']]);
        $this->save(['recommendation' => 'maybe'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');

        $this->assertSame(0, DB::table('recruitment_interview_scorecards')->count());
    }

    public function test_sending_needs_a_recommendation_and_is_safe_to_repeat(): void
    {
        $this->openInterview();

        $this->send()->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:recommendation-required');
        $this->save($this->card('yes'))->assertOk();
        $this->save(['recommendation' => null, 'answers' => []])->assertOk();
        $this->send()->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:recommendation-required');

        $this->save($this->card('strong_yes'))->assertOk();
        $this->send()->assertOk()->assertJsonPath('data.status', 'submitted');
        $first = $this->api('GET', "/company/interviews/{$this->interview}/scorecard", [], $this->recruiterToken)->json('data.submitted_at');
        $this->assertNotNull($first);

        $this->moveClockTo('2026-10-08 10:00:00');
        $this->send()->assertOk();
        $this->api('GET', "/company/interviews/{$this->interview}/scorecard", [], $this->recruiterToken)->assertJsonPath('data.submitted_at', $first);
    }

    public function test_a_scorecard_can_be_a_recommendation_alone_when_the_interview_has_no_questions(): void
    {
        $this->openInterview();
        $this->moveClockTo('2026-10-07 09:00:00');
        $this->api('PUT', "/company/interviews/{$this->interview}/questions", ['questions' => []], $this->recruiterToken)->assertOk();
        $this->moveClockTo('2026-10-08 09:30:00');

        $this->save(['recommendation' => 'no', 'note' => 'Not a fit.'])->assertOk()->assertJsonPath('data.score', null);
        $this->send()->assertOk();
        $this->sentList()->assertOk()->assertJsonPath('data.0.recommendation', 'no');
    }

    // ----- who sees the sent scorecards -----

    public function test_a_panel_member_sees_the_other_scorecards_only_after_sending_their_own(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 4, $this->recruiterToken);
        $this->save($this->card('no', 2, 2), $this->adminToken)->assertOk();

        $this->sentList($this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:scorecards-locked');
        $this->sentList($this->recruiterToken)->assertOk()->assertJsonCount(1, 'data');
        $this->sentList()->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.member.id', $this->recruiterId);

        $this->send($this->adminToken)->assertOk();

        $this->sentList($this->adminToken)->assertOk()->assertJsonCount(2, 'data');
        $this->sentList($this->otherAdminToken)->assertNotFound();
    }

    public function test_a_draft_is_never_listed(): void
    {
        $this->openInterview();
        $this->save($this->card())->assertOk();

        $this->sentList()->assertOk()->assertJsonCount(0, 'data');
    }

    // ----- edits after sending -----

    public function test_an_edit_of_a_sent_scorecard_keeps_the_previous_version(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 2, $this->recruiterToken);
        $id = (int) DB::table('recruitment_interview_scorecards')->value('id');

        $this->moveClockTo('2026-10-08 10:00:00');
        $edited = $this->save($this->card('strong_yes', 5, 4, 'Reconsidered.'))->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.recommendation', 'strong_yes');
        $this->assertNotNull($edited->json('data.edited_at'));

        $revisions = $this->api('GET', "/company/interviews/{$this->interview}/scorecards/{$id}/revisions", [], $this->secondRecruiterToken)->assertOk()->assertJsonCount(1, 'data');
        $revisions->assertJsonPath('data.0.previous.recommendation', 'yes')->assertJsonPath('data.0.previous.score', 3.88)->assertJsonPath('data.0.others_visible', false);
    }

    public function test_an_edit_that_changes_nothing_leaves_no_revision(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 2, $this->recruiterToken);

        $this->save($this->card('yes', 5, 2))->assertOk()->assertJsonPath('data.edited_at', null);

        $this->assertSame(0, DB::table('recruitment_interview_scorecard_revisions')->count());
    }

    public function test_an_edit_made_after_another_scorecard_was_sent_is_marked_and_the_history_is_newest_first(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 2, $this->recruiterToken);
        $id = (int) DB::table('recruitment_interview_scorecards')->where('member_id', $this->recruiterId)->value('id');

        $this->moveClockTo('2026-10-08 10:00:00');
        $this->save($this->card('strong_yes', 5, 3))->assertOk();
        $this->sent('no', 1, 1, $this->adminToken);

        $this->moveClockTo('2026-10-08 11:00:00');
        $this->save($this->card('yes', 4, 3))->assertOk();

        $history = $this->api('GET', "/company/interviews/{$this->interview}/scorecards/{$id}/revisions", [], $this->adminToken)->assertOk()->assertJsonCount(2, 'data');
        $history->assertJsonPath('data.0.others_visible', true)->assertJsonPath('data.0.previous.recommendation', 'strong_yes')
            ->assertJsonPath('data.1.others_visible', false)->assertJsonPath('data.1.previous.recommendation', 'yes');
    }

    public function test_a_sent_scorecard_keeps_its_recommendation_when_edited(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 2, $this->recruiterToken);

        $this->save(['recommendation' => null, 'answers' => []])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:recommendation-required');
    }

    public function test_the_history_follows_the_lock_rule_and_belongs_to_a_sent_scorecard_of_the_interview(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 2, $this->recruiterToken);
        $this->save($this->card('no'), $this->adminToken)->assertOk();
        $sent = (int) DB::table('recruitment_interview_scorecards')->where('member_id', $this->recruiterId)->value('id');
        $draft = (int) DB::table('recruitment_interview_scorecards')->where('member_id', $this->adminId)->value('id');

        $this->api('GET', "/company/interviews/{$this->interview}/scorecards/{$sent}/revisions", [], $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:scorecards-locked');
        $this->api('GET', "/company/interviews/{$this->interview}/scorecards/{$draft}/revisions", [], $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', "/company/interviews/{$this->interview}/scorecards/987654/revisions", [], $this->secondRecruiterToken)->assertNotFound();
    }

    public function test_nothing_changes_once_the_application_is_final(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 2, $this->recruiterToken);
        $this->save($this->card('no'), $this->adminToken)->assertOk();

        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();

        $this->save($this->card('strong_yes'))->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-final');
        $this->send($this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-final');
        $this->sentList()->assertOk()->assertJsonCount(1, 'data');
    }

    // ----- changing the panel -----

    private function panel(array $ids, ?string $token = null): TestResponse
    {
        return $this->api('PUT', "/company/interviews/{$this->interview}/participants", ['participant_ids' => $ids], $token ?? $this->recruiterToken);
    }

    public function test_a_member_who_leaves_the_panel_loses_the_draft_but_not_a_sent_scorecard(): void
    {
        $this->openInterview([$this->adminId, $this->secondRecruiterId]);
        $this->save($this->card('no'), $this->adminToken)->assertOk();
        $this->sent('yes', 5, 5, $this->secondRecruiterToken);

        $this->panel([])->assertOk()->assertJsonPath('data.participants', []);

        $this->assertSame(0, DB::table('recruitment_interview_scorecards')->where('member_id', $this->adminId)->count());
        $this->assertSame(1, DB::table('recruitment_interview_scorecards')->where('member_id', $this->secondRecruiterId)->where('status', 'submitted')->count());
        $this->assertSame(0, DB::table('recruitment_interview_participants')->where('interview_id', $this->interview)->count());

        $this->sentList($this->secondRecruiterToken)->assertOk()->assertJsonCount(1, 'data');
        $this->sentList($this->adminToken)->assertOk()->assertJsonCount(1, 'data');
        $this->api('GET', "/company/applications/{$this->applicationId}", [], $this->adminToken)->assertJsonPath('data.interviews.0.result.panel_size', 1);
    }

    public function test_a_member_who_joins_the_panel_must_send_before_seeing_the_others(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 5, $this->recruiterToken);

        $this->panel([$this->adminId, $this->secondRecruiterId])->assertOk()
            ->assertJsonPath('data.participants.1.id', $this->secondRecruiterId)
            ->assertJsonPath('data.participants.1.name', 'Sari Rekruter');

        $this->sentList($this->secondRecruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:scorecards-locked');
        $this->sent('no', 2, 2, $this->secondRecruiterToken);
        $this->sentList($this->secondRecruiterToken)->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_the_scheduler_the_interviewer_and_an_admin_change_the_panel_and_other_members_cannot(): void
    {
        $this->openInterview();

        $this->panel([], $this->secondRecruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:interview-edit-denied');
        $this->panel([], $this->otherAdminToken)->assertNotFound();
        $this->panel([$this->secondRecruiterId], $this->adminToken)->assertOk();
        $this->panel([$this->adminId])->assertOk()->assertJsonCount(1, 'data.participants');
    }

    public function test_the_panel_is_checked_like_when_scheduling(): void
    {
        $this->openInterview();

        $this->panel([$this->recruiterId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interviewer');
        $this->panel([$this->otherAdminId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interviewer');
        $this->panel([1, 2, 3, 4])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');
        $this->panel([$this->adminId, $this->adminId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');
        $this->api('PUT', "/company/interviews/{$this->interview}/participants", [], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');

        $this->assertSame([$this->adminId], DB::table('recruitment_interview_participants')->where('interview_id', $this->interview)->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_the_panel_of_a_cancelled_interview_or_a_final_application_cannot_change(): void
    {
        $this->openInterview();

        DB::table('recruitment_interviews')->where('id', $this->interview)->update(['status' => 'cancelled', 'active_key' => null]);
        $this->panel([])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-active');

        DB::table('recruitment_interviews')->where('id', $this->interview)->update(['status' => 'confirmed', 'active_key' => $this->applicationId]);
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();
        $this->panel([])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-final');
    }

    // ----- the questions lock -----

    public function test_a_started_scorecard_freezes_the_questions(): void
    {
        $this->openInterview();
        $this->api('GET', "/company/interviews/{$this->interview}/questions", [], $this->recruiterToken)->assertJsonPath('data.locked', false)->assertJsonPath('data.questions.0.id', $this->questionIds[0]);

        $this->save(['note' => 'Started.'])->assertOk();

        $this->api('GET', "/company/interviews/{$this->interview}/questions", [], $this->recruiterToken)->assertJsonPath('data.locked', true);
        $this->api('PUT', "/company/interviews/{$this->interview}/questions", ['questions' => []], $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:scoring-started');
        $this->assertCount(2, DB::table('recruitment_interview_questions')->where('interview_id', $this->interview)->get());
    }

    // ----- results -----

    public function test_the_results_show_in_the_detail_and_on_the_list_apart_from_the_stars(): void
    {
        $this->openInterview();
        $this->sent('strong_yes', 5, 5, $this->recruiterToken);

        $detail = $this->api('GET', "/company/applications/{$this->applicationId}", [], $this->secondRecruiterToken)->assertOk();
        $detail->assertJsonPath('data.interviews.0.id', $this->interview)
            ->assertJsonPath('data.interviews.0.result.score', 5)
            ->assertJsonPath('data.interviews.0.result.scorecards_sent', 1)
            ->assertJsonPath('data.interviews.0.result.panel_size', 2)
            ->assertJsonPath('data.interviews.0.result.recommendations.strong_yes', 1);

        $this->sent('no', 2, 2, $this->adminToken);

        $this->api('GET', "/company/applications/{$this->applicationId}", [], $this->secondRecruiterToken)
            ->assertJsonPath('data.interviews.0.result.score', 3.5)
            ->assertJsonPath('data.interviews.0.result.scorecards_sent', 2);

        $list = $this->api('GET', "/company/jobs/{$this->job}/applications", [], $this->secondRecruiterToken)->assertOk();
        $list->assertJsonPath('data.0.interview_result.score', 3.5)->assertJsonPath('data.0.interview_result.yes', 1)->assertJsonPath('data.0.interview_result.no', 1)->assertJsonPath('data.0.rating.count', 0);
    }

    public function test_a_panel_member_who_has_not_sent_sees_no_result(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 5, $this->recruiterToken);

        $this->api('GET', "/company/applications/{$this->applicationId}", [], $this->adminToken)->assertOk()->assertJsonPath('data.interviews.0.result', null);
        $this->api('GET', "/company/jobs/{$this->job}/applications", [], $this->adminToken)->assertOk()->assertJsonPath('data.0.interview_result', null);

        $this->api('GET', "/company/applications/{$this->applicationId}", [], $this->recruiterToken)->assertJsonPath('data.interviews.0.result.scorecards_sent', 1);
    }

    public function test_an_application_without_a_scored_interview_has_no_list_result(): void
    {
        $this->api('GET', "/company/jobs/{$this->job}/applications", [], $this->recruiterToken)->assertOk()->assertJsonPath('data.0.interview_result', null);
        $this->api('GET', "/company/applications/{$this->applicationId}", [], $this->recruiterToken)->assertOk()->assertJsonPath('data.interviews', []);
    }

    // ----- the reminder -----

    private function reminders(int $userId): int
    {
        return DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'interview.scorecard_reminder')->count();
    }

    public function test_a_day_after_the_interview_the_members_without_a_sent_scorecard_are_reminded_once(): void
    {
        $this->openInterview();
        $this->sent('yes', 5, 2, $this->recruiterToken);
        $reminders = $this->app->make(SendScorecardReminders::class);

        $this->moveClockTo('2026-10-09 09:30:00');
        $this->assertSame(0, $reminders->execute(), 'The interview ended at 10:00 on the 8th, so a day has not passed yet.');

        $this->moveClockTo('2026-10-09 10:01:00');
        $this->assertSame(1, $reminders->execute());
        $this->assertSame(0, $reminders->execute());

        $this->assertSame(1, $this->reminders($this->adminId));
        $this->assertSame(0, $this->reminders($this->recruiterId), 'The interviewer sent their scorecard.');
        $this->assertSame(0, $this->reminders($this->seekerId));
        $this->assertNotNull(DB::table('recruitment_interviews')->value('scorecard_reminded_at'));
    }

    public function test_the_hourly_command_sends_the_reminders(): void
    {
        $this->openInterview();
        $this->moveClockTo('2026-10-09 10:01:00');

        $this->artisan('recruitment:send-interview-reminders')->expectsOutput('Reminded 0 interviews.')->expectsOutput('Reminded 1 panel of missing scorecards.')->assertSuccessful();

        $this->assertSame(1, $this->reminders($this->recruiterId));
        $this->assertSame(1, $this->reminders($this->adminId));
    }

    public function test_an_old_interview_and_a_final_application_are_not_reminded(): void
    {
        $this->openInterview();
        $reminders = $this->app->make(SendScorecardReminders::class);

        $this->moveClockTo('2026-10-17 12:00:00');
        $this->assertSame(0, $reminders->execute(), 'Ended more than a week after the first day.');

        $this->moveClockTo('2026-10-09 10:01:00');
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();
        $this->assertSame(0, $reminders->execute());
        $this->assertSame(0, $this->reminders($this->adminId));
    }

    // ----- erasure -----

    public function test_erasing_the_applicant_keeps_scores_and_recommendations_and_clears_what_may_quote_them(): void
    {
        $this->openInterview();
        $this->save(['recommendation' => 'yes', 'note' => 'Siti was nervous.', 'answers' => [['question_id' => $this->questionIds[0], 'score' => 4, 'note' => 'Siti knew it.']]])->assertOk();
        $this->send()->assertOk();
        $this->save(['recommendation' => 'strong_yes', 'note' => 'Siti was great.', 'answers' => [['question_id' => $this->questionIds[0], 'score' => 5, 'note' => 'Siti again.']]])->assertOk();

        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);
        $this->app->make(EraseScorecardData::class)->execute($this->seekerId);

        $interview = DB::table('recruitment_interviews')->find($this->interview);
        $this->assertNotNull($interview);
        $this->assertNull($interview->meeting_url);
        $this->assertNull($interview->location);

        $card = DB::table('recruitment_interview_scorecards')->where('member_id', $this->recruiterId)->first();
        $this->assertSame('strong_yes', $card->recommendation);
        $this->assertSame('5.00', (string) $card->score);
        $this->assertNull($card->note);
        $this->assertSame(5, (int) DB::table('recruitment_interview_scorecard_answers')->where('scorecard_id', $card->id)->value('score'));
        $this->assertNull(DB::table('recruitment_interview_scorecard_answers')->where('scorecard_id', $card->id)->value('note'));
        $this->assertSame(0, DB::table('recruitment_interview_scorecard_revisions')->count());
    }

    public function test_erasing_the_applicant_still_deletes_an_interview_without_a_scorecard(): void
    {
        $this->openInterview();

        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        $this->assertSame(0, DB::table('recruitment_interviews')->count());
        $this->assertSame(0, DB::table('recruitment_interview_questions')->count());
    }
}
