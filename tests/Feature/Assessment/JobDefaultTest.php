<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Assessment\Application\Defaults\AdvanceOnFinalResult;
use Src\Assessment\Domain\Events\ResultFinalized;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * `docs/design/phase-8-assessment-automation.md` sections 1 and 2: the default test of a job per assessment stage (set in the job editor), sent when an application moves into
 * the stage without a test, and the rule that moves an application on by itself when a skill test is final with a score at or above the threshold.
 */
final class JobDefaultTest extends FeatureTestCase
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

    /** A published skill test of the company with these questions (a single choice question worth 2 points when none are given). @param  list<array<string, mixed>>  $questions */
    private function publishedTest(string $title = 'Logic and numbers', ?array $questions = null): int
    {
        $id = $this->newTest('company', ['title' => $title]);
        $section = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        foreach ($questions ?? [$this->choice()] as $question) {
            $this->company('POST', "sections/{$section}/questions", $question)->assertCreated();
        }

        $this->company('POST', "tests/{$id}/publish")->assertOk();

        return $id;
    }

    /** A published psychological battery granted to the company. */
    private function battery(): int
    {
        $id = $this->newTest('provider', ['title' => 'Personality']);
        $section = (int) $this->provider('POST', "tests/{$id}/sections", $this->section('Statements', 300))->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Sometimes', 'score_value' => 2], ['label' => 'Always', 'score_value' => 3]],
        ])->assertCreated();
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 50, 'band' => 'Average']]])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Dear {{candidate_name}}'])->assertOk();
        $this->provider('POST', "tests/{$id}/publish")->assertOk();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertNoContent();
        $this->overrideSetting('assessment.psychological_enabled', true);

        return $id;
    }

    /** @param  array<string, mixed>  $body */
    private function putDefault(int $stageId, array $body = [], ?string $token = null, ?int $job = null): TestResponse
    {
        return $this->api('PUT', '/company/jobs/'.($job ?? $this->job).'/assessments/'.$stageId, ['test_id' => $this->testId, ...$body], $token ?? $this->recruiterToken);
    }

    private function defaults(?string $token = null, ?int $job = null): TestResponse
    {
        return $this->api('GET', '/company/jobs/'.($job ?? $this->job).'/assessments', [], $token ?? $this->recruiterToken);
    }

    private function move(int $stageId, ?int $id = null): TestResponse
    {
        return $this->api('POST', '/company/applications/'.($id ?? $this->applicationId).'/move', ['stage_id' => $stageId], $this->recruiterToken);
    }

    private function stageType(): string
    {
        return (string) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type');
    }

    /** The candidate takes the test of the latest invitation: answers by question type, then submits. @param  array<string, array<string, mixed>>  $answers */
    private function take(array $answers): int
    {
        $invitation = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');
        $uri = "/me/assessment/invitations/{$invitation}";
        $session = (string) $this->api('POST', "{$uri}/start", ['consent' => true], $this->seekerToken)->assertCreated()->json('data.attempt_session');
        $headers = ['X-Attempt-Session' => $session];

        foreach ($this->api('GET', "{$uri}/attempt", [], $this->seekerToken, $headers)->json('data.questions') as $question) {
            if (isset($answers[$question['type']])) {
                $this->api('PUT', "{$uri}/attempt/questions/{$question['id']}/answer", $answers[$question['type']], $this->seekerToken, $headers)->assertNoContent();
            }
        }

        $this->api('POST', "{$uri}/attempt/submit", [], $this->seekerToken, $headers)->assertOk();

        return $invitation;
    }

    /** @return list<array{0: string, 1: string|null}> the history of the application as actor and note */
    private function history(): array
    {
        return DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->orderBy('id')->get()
            ->map(fn (object $row): array => [(string) $row->actor_kind, $row->note])->all();
    }

    /** @return list<string> */
    private function notificationTypes(int $userId, string $like = 'application.auto_advanced'): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', $like)->pluck('type')->all();
        sort($types);

        return $types;
    }

    /** An interview stage of another company (the other company has none until it gets one). */
    private function otherCompanyInterviewStage(): int
    {
        return (int) DB::table('recruitment_stages')->insertGetId(['company_id' => $this->otherCompanyId, 'name' => 'Other interview', 'type' => 'interview', 'position' => 1, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    // ----- access -----

    public function test_the_routes_need_a_signed_in_member_who_may_send_tests_and_see_the_job(): void
    {
        $stage = $this->stage('assessment');
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/company/jobs/{$this->job}/assessments")->assertUnauthorized();

        $this->api('GET', "/company/jobs/{$this->job}/assessments", [], $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');

        $limited = $this->memberWith(['jobs.view_all'], 'viewer@example.test');
        $this->defaults($limited['token'])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->putDefault($stage, [], $limited['token'])->assertForbidden();

        // A recruiter who is not assigned to the job, and another company's admin, do not even see that it exists.
        $this->defaults($this->secondRecruiterToken)->assertNotFound();
        $this->putDefault($stage, [], $this->otherAdminToken)->assertNotFound();
        $this->api('DELETE', "/company/jobs/{$this->job}/assessments/{$stage}", [], $this->otherAdminToken)->assertNotFound();
        $this->defaults(job: 999_999)->assertNotFound();
    }

    // ----- setting and reading -----

    public function test_a_default_is_set_read_replaced_and_removed(): void
    {
        $stage = $this->stage('assessment');
        $this->defaults()->assertOk()->assertExactJson(['data' => []]);

        $this->putDefault($stage, ['due_days' => 2, 'assessor_ids' => [$this->adminId]])->assertOk()
            ->assertJsonPath('data.stage_id', $stage)
            ->assertJsonPath('data.stage_name', DB::table('recruitment_stages')->where('id', $stage)->value('name'))
            ->assertJsonPath('data.test_id', $this->testId)
            ->assertJsonPath('data.test_title', 'Logic and numbers')
            ->assertJsonPath('data.due_days', 2)
            ->assertJsonPath('data.assessors', [['user_id' => $this->adminId, 'name' => 'Andi Admin']])
            ->assertJsonPath('data.advance', null)
            ->assertJsonPath('data.problem', null);

        $interview = $this->stage('interview');
        $other = $this->publishedTest('Second test');
        $this->putDefault($stage, ['test_id' => $other, 'advance' => ['min_percentage' => 70, 'to_stage_id' => $interview]])->assertOk()
            ->assertJsonPath('data.due_days', 3)
            ->assertJsonPath('data.assessors', [])
            ->assertJsonPath('data.advance', ['min_percentage' => 70, 'to_stage_id' => $interview, 'to_stage_name' => DB::table('recruitment_stages')->where('id', $interview)->value('name')]);

        $this->assertSame(1, DB::table('assessment_job_stage_tests')->count());
        $this->assertSame(0, DB::table('assessment_job_stage_test_assessors')->count());
        $list = $this->defaults()->assertOk()->json('data');
        $this->assertSame([[$stage, $other, 3, 70]], array_map(fn (array $row): array => [$row['stage_id'], $row['test_id'], $row['due_days'], $row['advance']['min_percentage']], $list));

        $this->api('DELETE', "/company/jobs/{$this->job}/assessments/{$stage}", [], $this->recruiterToken)->assertNoContent();
        $this->api('DELETE', "/company/jobs/{$this->job}/assessments/{$stage}", [], $this->recruiterToken)->assertNoContent();
        $this->defaults()->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_the_default_of_one_job_is_not_the_default_of_another(): void
    {
        $other = $this->insertJob('Frontend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);

        $this->putDefault($this->stage('assessment'))->assertOk();

        $this->defaults(job: $other)->assertOk()->assertExactJson(['data' => []]);
    }

    // ----- what a default must be -----

    public function test_the_stage_must_be_an_assessment_stage_of_the_company(): void
    {
        foreach ([$this->stage('interview'), $this->stage('applied'), 999_999] as $stage) {
            $this->putDefault($stage)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:stage-not-assessment')->assertJsonStructure(['errors' => ['stage_id']]);
        }

        $theirs = (int) DB::table('recruitment_stages')->where('company_id', $this->otherCompanyId)->where('type', 'assessment')->value('id');
        $this->putDefault($theirs)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:stage-not-assessment');
        $this->assertSame(0, DB::table('assessment_job_stage_tests')->count());
    }

    public function test_the_test_the_people_and_the_days_are_checked_like_a_test_sent_by_hand(): void
    {
        $stage = $this->stage('assessment');
        $draft = $this->newTest('company', ['title' => 'Draft']);
        $written = $this->publishedTest('Written', [['type' => 'long_text', 'text' => 'Explain a recent project.', 'points' => 5]]);

        foreach ([
            [['test_id' => $draft], 'invalid-assessment-test', 'test_id'],
            [['test_id' => 999_999], 'invalid-assessment-test', 'test_id'],
            [['due_days' => 31], 'invalid-assessment-schedule', 'due_days'],
            [['assessor_ids' => [$this->secondRecruiterId]], 'invalid-assessor', 'assessor_ids'],
            [['assessor_ids' => [$this->otherAdminId]], 'invalid-assessor', 'assessor_ids'],
            [['test_id' => $written], 'invalid-assessor', 'assessor_ids'],
        ] as [$body, $code, $field]) {
            $this->putDefault($stage, $body)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:'.$code)->assertJsonStructure(['errors' => [$field]]);
        }

        $this->putDefault($stage, ['test_id' => $written, 'assessor_ids' => [$this->adminId]])->assertOk();
        $this->assertSame(1, DB::table('assessment_job_stage_tests')->count());
    }

    public function test_the_shape_of_the_request_is_checked(): void
    {
        $stage = $this->stage('assessment');

        $this->api('PUT', "/company/jobs/{$this->job}/assessments/{$stage}", [], $this->recruiterToken)->assertStatus(422)->assertJsonValidationErrors(['test_id']);

        foreach ([
            ['due_days' => 0], ['due_days' => 366], ['assessor_ids' => range(1, 6)], ['assessor_ids' => [1, 1]],
            ['advance' => ['min_percentage' => 0, 'to_stage_id' => 1]], ['advance' => ['min_percentage' => 101, 'to_stage_id' => 1]], ['advance' => ['min_percentage' => 50]], ['advance' => ['to_stage_id' => 1]],
        ] as $body) {
            $this->putDefault($stage, $body)->assertStatus(422);
        }
    }

    public function test_the_advance_rule_is_for_a_skill_test_and_leads_to_an_assessment_or_interview_stage(): void
    {
        $stage = $this->stage('assessment');
        $interview = $this->stage('interview');
        $battery = $this->battery();

        $this->putDefault($stage, ['test_id' => $battery, 'advance' => ['min_percentage' => 60, 'to_stage_id' => $interview]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:advance-not-allowed');
        $this->putDefault($stage, ['test_id' => $battery])->assertOk()->assertJsonPath('data.advance', null);

        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'Second test', 'type' => 'assessment'], $this->adminToken)->assertCreated()->json('data.id');

        foreach ([$this->stage('offer'), $this->stage('hired'), $this->stage('rejected'), $this->stage('applied'), $stage, 999_999, $this->otherCompanyInterviewStage()] as $target) {
            $this->putDefault($stage, ['advance' => ['min_percentage' => 60, 'to_stage_id' => $target]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-advance-stage')->assertJsonStructure(['errors' => ['advance.to_stage_id']]);
        }

        $this->putDefault($stage, ['advance' => ['min_percentage' => 60, 'to_stage_id' => $extra]])->assertOk()->assertJsonPath('data.advance.to_stage_id', $extra);
        $this->putDefault($stage, ['advance' => null])->assertOk()->assertJsonPath('data.advance', null);
    }

    public function test_a_default_that_no_longer_works_is_reported_with_its_problem(): void
    {
        $stage = $this->stage('assessment');
        $this->putDefault($stage, ['assessor_ids' => [$this->adminId]])->assertOk();

        $this->company('POST', "tests/{$this->testId}/archive")->assertOk();
        $this->defaults()->assertOk()->assertJsonPath('data.0.problem', 'invalid-assessment-test');
    }

    // ----- sending the default -----

    public function test_a_move_into_the_stage_without_a_test_sends_the_default(): void
    {
        $stage = $this->stage('assessment');
        $this->api('PUT', '/company/message-defaults', ['assessment' => 'Use a quiet room.'], $this->adminToken)->assertOk();
        $this->putDefault($stage, ['due_days' => 2, 'assessor_ids' => [$this->adminId]])->assertOk();

        $this->move($stage)->assertOk();

        $this->assertSame('assessment', $this->stageType());
        $row = DB::table('assessment_invitations')->first();
        $this->assertSame(
            ['invited', $this->testId, $this->recruiterId, $stage, 'Use a quiet room.', now()->format('Y-m-d H:i:s'), now()->addDays(2)->format('Y-m-d H:i:s')],
            [$row->status, (int) $row->test_id, (int) $row->invited_by_user_id, (int) $row->stage_id, $row->note, $row->starts_at, $row->due_at],
        );
        $this->assertSame([$this->adminId], DB::table('assessment_invitation_assessors')->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(['assessment.invited'], $this->notificationTypes($this->seekerId, 'assessment.%'));
    }

    public function test_the_window_of_a_default_never_falls_short_of_the_company_minimum_or_the_test_duration(): void
    {
        $stage = $this->stage('assessment');
        $this->overrideSetting('assessment.min_due_hours', 72);
        $this->putDefault($stage, ['due_days' => 1])->assertOk();

        $this->move($stage)->assertOk();

        $due = (string) DB::table('assessment_invitations')->value('due_at');
        $this->assertGreaterThanOrEqual(now()->addHours(72)->format('Y-m-d H:i:s'), $due);
        $this->assertLessThan(now()->addHours(73)->format('Y-m-d H:i:s'), $due);
    }

    public function test_a_test_in_the_request_wins_over_the_default_and_an_active_test_is_kept(): void
    {
        $stage = $this->stage('assessment');
        $this->putDefault($stage)->assertOk();
        $other = $this->publishedTest('Chosen by hand');

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => ['test_id' => $other, 'due_at' => now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z')]], $this->recruiterToken)->assertOk();
        $this->assertSame([$other], DB::table('assessment_invitations')->pluck('test_id')->map(fn ($id): int => (int) $id)->all());

        // Moving into another assessment stage keeps nothing of the old test: the application is already in an assessment stage with an active test, so the move keeps it.
        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'Second test', 'type' => 'assessment'], $this->adminToken)->assertCreated()->json('data.id');
        $this->move($extra)->assertOk();
        $this->assertSame(1, DB::table('assessment_invitations')->count());
    }

    public function test_a_stage_without_a_default_still_needs_a_test(): void
    {
        $this->move($this->stage('assessment'))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:assessment-required');

        $this->putDefault($this->stage('assessment'))->assertOk();
        $this->api('DELETE', "/company/jobs/{$this->job}/assessments/{$this->stage('assessment')}", [], $this->recruiterToken)->assertNoContent();
        $this->move($this->stage('assessment'))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:assessment-required');
        $this->assertSame('applied', $this->stageType());
    }

    public function test_a_default_that_no_longer_works_stops_a_single_move_and_skips_the_application_in_a_bulk_move(): void
    {
        $stage = $this->stage('assessment');
        $this->putDefault($stage)->assertOk();
        $this->company('POST', "tests/{$this->testId}/archive")->assertOk();

        $this->move($stage)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:assessment-default-invalid');
        $this->assertSame('applied', $this->stageType());
        $this->assertSame(0, DB::table('assessment_invitations')->count());

        $response = $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", ['application_ids' => [$this->applicationId], 'stage_id' => $stage], $this->recruiterToken)->assertOk();
        $this->assertSame(['assessment-default-invalid'], array_column($response->json('data'), 'code'));
        $this->assertSame('applied', $this->stageType());
    }

    public function test_a_bulk_move_sends_the_default_to_every_application(): void
    {
        $stage = $this->stage('assessment');
        $this->putDefault($stage)->assertOk();
        $token = $this->tokenFor($this->seeker('second.candidate@example.test'));
        $this->resumeId = $this->completeProfile($token);
        $second = (int) $this->apply($this->job, token: $token)->assertCreated()->json('data.id');

        $response = $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", ['application_ids' => [$this->applicationId, $second], 'stage_id' => $stage], $this->recruiterToken)->assertOk();

        $this->assertSame([null, null], array_column($response->json('data'), 'code'));
        $this->assertSame(2, DB::table('assessment_invitations')->where('stage_id', $stage)->count());
    }

    public function test_a_clone_keeps_the_defaults(): void
    {
        $stage = $this->stage('assessment');
        $interview = $this->stage('interview');
        $this->putDefault($stage, ['due_days' => 5, 'assessor_ids' => [$this->adminId], 'advance' => ['min_percentage' => 80, 'to_stage_id' => $interview]])->assertOk();

        $copy = (int) $this->api('POST', "/company/jobs/{$this->job}/clone", [], $this->recruiterToken)->assertCreated()->json('data.id');

        $row = $this->defaults(job: $copy)->assertOk()->json('data.0');
        $this->assertSame([$stage, $this->testId, 5, [$this->adminId], 80, $interview], [$row['stage_id'], $row['test_id'], $row['due_days'], array_column($row['assessors'], 'user_id'), $row['advance']['min_percentage'], $row['advance']['to_stage_id']]);
        $this->assertSame($this->recruiterId, (int) DB::table('assessment_job_stage_tests')->where('job_id', $copy)->value('updated_by'));
    }

    // ----- moving on by itself -----

    private function withAdvance(int $min, string $target = 'interview', ?int $test = null): void
    {
        $this->putDefault($this->stage('assessment'), ['test_id' => $test ?? $this->testId, 'assessor_ids' => [$this->adminId], 'advance' => ['min_percentage' => $min, 'to_stage_id' => $this->stage($target)]])->assertOk();
    }

    public function test_a_final_score_at_or_above_the_threshold_moves_the_application_to_an_interview_stage_by_itself(): void
    {
        $this->withAdvance(60);
        $this->move($this->stage('assessment'))->assertOk();

        $this->take(['single_choice' => ['option_positions' => [1]]]);

        $this->assertSame('interview', $this->stageType());
        $this->assertSame([['recruiter', null], ['system', 'Otomatis: skor 100% ≥ 60%']], array_slice($this->history(), -2));
        $this->assertSame(0, DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->count(), 'The team schedules the interview later.');
        $this->assertSame(['application.auto_advanced'], $this->notificationTypes($this->recruiterId));
        $this->assertSame([], $this->notificationTypes($this->seekerId, 'application.auto_advanced'));
        $data = json_decode((string) DB::table('notifications')->where('type', 'application.auto_advanced')->value('data'), true);
        $this->assertSame([$this->applicationId, $this->job, $this->stage('assessment'), $this->stage('interview'), 100.0], [$data['application_id'], $data['job_id'], $data['from_stage_id'], $data['to_stage_id'], (float) $data['percentage']]);
    }

    public function test_a_score_below_the_threshold_leaves_the_application_where_it_is(): void
    {
        $test = $this->publishedTest('Two questions', [$this->choice(), ['type' => 'number', 'text' => 'What is 6 times 7?', 'points' => 1, 'options' => [['label' => '42']]]]);
        $this->withAdvance(70, test: $test);
        $this->move($this->stage('assessment'))->assertOk();

        $this->take(['single_choice' => ['option_positions' => [1]]]);

        $this->assertSame('assessment', $this->stageType());
        $this->assertSame([], $this->notificationTypes($this->recruiterId));
    }

    public function test_the_threshold_counts_when_the_score_is_exactly_on_it(): void
    {
        $test = $this->publishedTest('Two questions', [$this->choice(), ['type' => 'number', 'text' => 'What is 6 times 7?', 'points' => 2, 'options' => [['label' => '42']]]]);
        $this->withAdvance(50, test: $test);
        $this->move($this->stage('assessment'))->assertOk();

        // 2 of 4 points is exactly 50 %.
        $this->take(['single_choice' => ['option_positions' => [1]]]);

        $this->assertSame('interview', $this->stageType());
        $this->assertSame(['system', 'Otomatis: skor 50% ≥ 50%'], $this->history()[array_key_last($this->history())]);
    }

    public function test_the_result_waits_for_the_assessor_and_then_the_application_moves(): void
    {
        $test = $this->publishedTest('Written', [['type' => 'long_text', 'text' => 'Explain a recent project.', 'points' => 5]]);
        $this->withAdvance(60, test: $test);
        $this->move($this->stage('assessment'))->assertOk();

        $invitation = $this->take(['long_text' => ['text' => 'I built the job portal.']]);
        $this->assertSame('assessment', $this->stageType(), 'A long-text answer has no score yet, so the result is not final.');

        $question = (int) DB::table('assessment_questions')->where('type', 'long_text')->value('id');
        $this->company('PUT', "invitations/{$invitation}/scoring/questions/{$question}", ['score' => 2], $this->adminToken)->assertOk();
        $this->assertSame('assessment', $this->stageType(), '2 of 5 is 40 %, below the threshold.');

        $this->company('PUT', "invitations/{$invitation}/scoring/questions/{$question}", ['score' => 4], $this->adminToken)->assertOk();
        $this->assertSame('interview', $this->stageType());
        $this->assertSame(['system', 'Otomatis: skor 80% ≥ 60%'], $this->history()[array_key_last($this->history())]);

        // Correcting the score later does not move it again: the application has left the stage.
        $this->company('PUT', "invitations/{$invitation}/scoring/questions/{$question}", ['score' => 5], $this->adminToken)->assertStatus(409);
        $this->assertCount(3, $this->history());
    }

    public function test_a_second_run_for_the_same_result_does_nothing_more(): void
    {
        $this->withAdvance(60);
        $this->move($this->stage('assessment'))->assertOk();
        $invitation = $this->take(['single_choice' => ['option_positions' => [1]]]);
        $this->assertSame('interview', $this->stageType());

        $this->app->make(AdvanceOnFinalResult::class)->handle(new ResultFinalized($invitation, $this->applicationId, $this->job, $this->companyId, 100.0, 'none', now()->toDateTimeImmutable()));

        $this->assertCount(3, $this->history());
        $this->assertSame(['application.auto_advanced'], $this->notificationTypes($this->recruiterId));
    }

    public function test_a_major_integrity_tier_leaves_the_decision_to_a_person(): void
    {
        $this->withAdvance(60);
        $this->move($this->stage('assessment'))->assertOk();
        $invitation = $this->take(['single_choice' => ['option_positions' => [1]]]);
        $this->assertSame('interview', $this->stageType());

        // The same result with five integrity signals, for an application that is back in the stage.
        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['stage_id' => $this->stage('assessment'), 'stage_type' => 'assessment']);
        $historyBefore = count($this->history());

        $this->app->make(AdvanceOnFinalResult::class)->handle(new ResultFinalized($invitation, $this->applicationId, $this->job, $this->companyId, 100.0, 'major', now()->toDateTimeImmutable()));

        $this->assertSame('assessment', $this->stageType());
        $this->assertCount($historyBefore, $this->history());
    }

    public function test_a_test_without_an_advance_rule_or_sent_by_hand_never_moves_the_application(): void
    {
        $stage = $this->stage('assessment');
        $this->putDefault($stage)->assertOk();
        $this->move($stage)->assertOk();
        $this->take(['single_choice' => ['option_positions' => [1]]]);
        $this->assertSame('assessment', $this->stageType());

        // A test sent by hand outside the move has no stage, so a rule set afterwards does not apply to it.
        $this->withAdvance(10);
        DB::table('assessment_invitations')->update(['stage_id' => null]);
        $this->app->make(AdvanceOnFinalResult::class)->handle(new ResultFinalized((int) DB::table('assessment_invitations')->value('id'), $this->applicationId, $this->job, $this->companyId, 100.0, 'none', now()->toDateTimeImmutable()));
        $this->assertSame('assessment', $this->stageType());
    }

    public function test_an_application_that_moved_on_is_not_moved_by_a_late_result(): void
    {
        $this->withAdvance(60);
        $this->move($this->stage('assessment'))->assertOk();
        $invitation = (int) DB::table('assessment_invitations')->value('id');

        // The team moves the application on before the result is final; the test is cancelled with the move.
        $this->move($this->stage('screening'))->assertOk();
        DB::table('assessment_invitations')->where('id', $invitation)->update(['status' => 'submitted']);
        $this->app->make(AdvanceOnFinalResult::class)->handle(new ResultFinalized($invitation, $this->applicationId, $this->job, $this->companyId, 100.0, 'none', now()->toDateTimeImmutable()));

        $this->assertSame('screening', $this->stageType());
        $this->assertSame([], $this->notificationTypes($this->recruiterId));
    }

    public function test_an_application_can_chain_into_the_default_test_of_another_assessment_stage(): void
    {
        $first = $this->stage('assessment');
        $second = (int) $this->api('POST', '/company/stages', ['name' => 'Second test', 'type' => 'assessment'], $this->adminToken)->assertCreated()->json('data.id');
        $next = $this->publishedTest('Second round');
        $this->putDefault($second, ['test_id' => $next, 'due_days' => 4])->assertOk();
        $this->putDefault($first, ['assessor_ids' => [$this->adminId], 'advance' => ['min_percentage' => 60, 'to_stage_id' => $second]])->assertOk();
        $this->move($first)->assertOk();

        $this->take(['single_choice' => ['option_positions' => [1]]]);

        $this->assertSame('assessment', $this->stageType());
        $this->assertSame($second, (int) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_id'));
        $row = DB::table('assessment_invitations')->orderByDesc('id')->first();
        $this->assertSame(['invited', $next, $second, $this->recruiterId, now()->addDays(4)->format('Y-m-d H:i:s')], [$row->status, (int) $row->test_id, (int) $row->stage_id, (int) $row->invited_by_user_id, $row->due_at]);
        $this->assertSame(['submitted', 'invited'], DB::table('assessment_invitations')->orderBy('id')->pluck('status')->all());
    }

    public function test_a_chain_into_a_stage_without_a_usable_default_does_not_move_the_application(): void
    {
        $first = $this->stage('assessment');
        $second = (int) $this->api('POST', '/company/stages', ['name' => 'Second test', 'type' => 'assessment'], $this->adminToken)->assertCreated()->json('data.id');
        $this->putDefault($first, ['assessor_ids' => [$this->adminId], 'advance' => ['min_percentage' => 60, 'to_stage_id' => $second]])->assertOk();
        $this->move($first)->assertOk();

        $this->take(['single_choice' => ['option_positions' => [1]]]);

        $this->assertSame($first, (int) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_id'));
        $this->assertSame([], $this->notificationTypes($this->recruiterId));
    }

    public function test_deleting_the_stage_takes_its_default_away(): void
    {
        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'Second test', 'type' => 'assessment'], $this->adminToken)->assertCreated()->json('data.id');
        $this->putDefault($extra)->assertOk();
        $this->assertSame(1, DB::table('assessment_job_stage_tests')->count());

        $this->api('DELETE', "/company/stages/{$extra}", [], $this->adminToken)->assertSuccessful();

        $this->assertSame(0, DB::table('assessment_job_stage_tests')->count());
    }
}
