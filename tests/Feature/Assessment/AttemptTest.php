<?php

namespace Tests\Feature\Assessment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Assessment\Domain\ColumnDigits;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * Phase 6, part 5: the candidate takes a test. The server keeps the time and the navigation rules, saves the answers, takes the Kraepelin columns and the
 * drawings, notes the integrity signals, scores the attempt when it is submitted and submits it when the time runs out. The clock is frozen at 09:00.
 */
final class AttemptTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $job;

    private int $applicationId;

    private int $invitationId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
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

    private function at(Carbon $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Sends the test to the candidate by moving the application to the assessment stage, and remembers the invitation.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function invite(int $testId, array $overrides = []): void
    {
        $assessment = ['test_id' => $testId, 'due_at' => $this->at(now()->addDays(3)), ...$overrides];

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('assessment'), 'assessment' => $assessment], $this->recruiterToken)->assertNoContent();
        $this->invitationId = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');
    }

    /** @param  list<array<string, mixed>>  $questions */
    private function skillTest(array $questions, array $section = [], string $title = 'Logic and numbers'): int
    {
        $id = $this->newTest('company', ['title' => $title]);
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section('Basics', 600, $section))->assertCreated()->json('data.id');

        foreach ($questions as $question) {
            $this->company('POST', "sections/{$sectionId}/questions", $question)->assertCreated();
        }

        $this->company('POST', "tests/{$id}/publish")->assertOk();

        return $id;
    }

    /** @return array<string, mixed> */
    private function number(): array
    {
        return ['type' => 'number', 'text' => 'What is 6 times 7?', 'points' => 1, 'options' => [['label' => '42']]];
    }

    /** @return array<string, mixed> */
    private function shortText(): array
    {
        return ['type' => 'short_text', 'text' => 'The capital of Indonesia?', 'points' => 1, 'options' => [['label' => 'Jakarta']]];
    }

    /** @return array<string, mixed> */
    private function multiple(): array
    {
        return ['type' => 'multiple_response', 'text' => 'Which are primes?', 'points' => 3, 'options' => [['label' => '2', 'is_correct' => true], ['label' => '3', 'is_correct' => true], ['label' => '4']]];
    }

    /** @return array<string, mixed> */
    private function longText(): array
    {
        return ['type' => 'long_text', 'text' => 'Explain a recent project.', 'points' => 5];
    }

    /** The token of the device that started the attempt, sent with every later call. */
    private ?string $attemptSession = null;

    /** @param  array<string, mixed>  $body */
    private function take(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $response = $this->api($method, "/me/assessment/invitations/{$this->invitationId}/{$uri}", $body, $token ?? $this->seekerToken, $this->attemptSession === null ? [] : ['X-Attempt-Session' => $this->attemptSession]);

        if ($uri === 'start' && is_string($session = $response->json('data.attempt_session'))) {
            $this->attemptSession = $session;
        }

        return $response;
    }

    private function start(?string $token = null): TestResponse
    {
        return $this->take('POST', 'start', ['consent' => true], $token);
    }

    private function attempt(): TestResponse
    {
        return $this->take('GET', 'attempt');
    }

    /** @param  array<string, mixed>  $body */
    private function answer(int $questionId, array $body): TestResponse
    {
        return $this->take('PUT', "attempt/questions/{$questionId}/answer", $body);
    }

    /** @return list<int> the ids of the questions of the open section, in the order they are asked */
    private function questionIds(): array
    {
        return array_map('intval', array_column($this->attempt()->json('data.questions'), 'id'));
    }

    private function moveClock(int $seconds): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00')->addSeconds($seconds));
    }

    /** @return array<string, mixed> */
    private function storedResult(): array
    {
        $row = DB::table('assessment_results')->first();
        $this->assertNotNull($row);

        return (array) $row;
    }

    /** @return list<string> */
    private function notificationTypes(int $userId): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', 'assessment.%')->pluck('type')->all();
        sort($types);

        return $types;
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(120, 80);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 20, 20));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** @param  array<string, mixed>  $fields */
    private function sendFile(string $uri, string $contents, array $fields = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->seekerToken)->post(
            "/api/v1/me/assessment/invitations/{$this->invitationId}/{$uri}",
            ['file' => UploadedFile::fake()->createWithContent('drawing.png', $contents), 'source' => 'canvas', ...$fields],
            ['Accept' => 'application/json', ...($this->attemptSession === null ? [] : ['X-Attempt-Session' => $this->attemptSession])],
        );
    }

    /** A battery of three sections: Likert statements, two Kraepelin columns and a drawing. It is granted to the company. */
    private function battery(): int
    {
        $id = $this->newTest('provider', ['title' => 'Personality']);

        $likert = (int) $this->provider('POST', "tests/{$id}/sections", $this->section('Statements', 300))->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$likert}/questions", [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Sometimes', 'score_value' => 2], ['label' => 'Always', 'score_value' => 3]],
        ])->assertCreated();
        $this->provider('POST', "sections/{$likert}/questions", [
            'type' => 'forced_choice', 'text' => 'Which describes you better?',
            'options' => [['label' => 'I plan ahead', 'score_value' => 2, 'scale_key' => 'order'], ['label' => 'I go with the flow', 'score_value' => 2, 'scale_key' => 'flexibility']],
        ])->assertCreated();

        $this->provider('POST', "tests/{$id}/sections", ['title' => 'Columns', 'format' => 'speed_columns', 'settings' => ['columns' => 2, 'digits_per_column' => 10, 'seconds_per_column' => 30]])->assertCreated();

        $drawing = (int) $this->provider('POST', "tests/{$id}/sections", ['title' => 'Draw a tree', 'format' => 'drawing', 'duration_seconds' => 900])->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$drawing}/questions", ['type' => 'drawing', 'text' => 'Draw a tree as you see it.'])->assertCreated();

        $norms = array_map(fn (string $scale): array => ['scale_key' => $scale, 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 50, 'band' => 'Average'], ['extraversion', 'order', 'flexibility']);
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => $norms])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Dear {{candidate_name}}'])->assertOk();
        $this->provider('POST', "tests/{$id}/publish")->assertOk();

        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertNoContent();
        $this->overrideSetting('assessment.psychological_enabled', true);

        return $id;
    }

    // ----- starting -----

    public function test_a_candidate_agrees_and_starts_and_sees_the_questions_without_the_answers(): void
    {
        $this->invite($this->skillTest([$this->choice(), $this->number(), $this->shortText()]));

        $this->take('POST', 'start', [])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:consent-required')->assertJsonStructure(['errors' => ['consent']]);
        $this->take('POST', 'start', ['consent' => false])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:consent-required');
        $this->take('GET', 'attempt')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-not-started');

        $response = $this->start()->assertCreated()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.section.position', 1)
            ->assertJsonPath('data.section.total', 1)
            ->assertJsonPath('data.section.format', 'questions')
            ->assertJsonPath('data.section.duration_seconds', 600)
            ->assertJsonPath('data.section.question_count', 3)
            ->assertJsonPath('data.server_time', '2026-10-05T09:00:00+00:00')
            ->assertJsonPath('data.ends_at', '2026-10-05T09:10:00+00:00')
            ->assertJsonCount(3, 'data.questions')
            ->assertJsonPath('data.questions.0.options.0.label', '7')
            ->assertJsonPath('data.questions.1.options', []);

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertStringNotContainsString('score', $response->getContent());
        $this->assertSame(['started'], DB::table('assessment_invitations')->pluck('status')->all());
        $this->assertSame(1, DB::table('assessment_attempts')->count());

        // A second start changes nothing.
        $this->start()->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->assertSame(1, DB::table('assessment_attempts')->count());
    }

    public function test_only_the_candidate_starts_their_test_and_only_once_it_is_open(): void
    {
        $this->invite($this->skillTest([$this->choice()]), ['starts_at' => $this->at(now()->addHours(1)), 'due_at' => $this->at(now()->addDays(2))]);

        $this->start()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:invitation-not-open');

        $other = $this->tokenFor($this->seeker('rina.lain@example.test'));
        $this->start($other)->assertNotFound();
        $this->start($this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');

        $this->moveClock(3601);
        $this->start()->assertCreated();
    }

    public function test_an_expired_or_cancelled_test_cannot_be_started(): void
    {
        $this->invite($this->skillTest([$this->choice()]), ['due_at' => $this->at(now()->addHours(2))]);

        $this->moveClock(3 * 3600);
        $this->start()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:invitation-not-open');

        $this->moveClock(0);
        $this->company('POST', "invitations/{$this->invitationId}/cancel", [], $this->recruiterToken)->assertOk();
        $this->start()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:invitation-not-open');
    }

    public function test_a_candidate_may_start_up_to_the_due_time_and_finish_after_it(): void
    {
        $this->invite($this->skillTest([$this->choice()]), ['due_at' => $this->at(now()->addHours(2))]);

        $this->moveClock(2 * 3600 - 60);
        $this->start()->assertCreated();

        $this->moveClock(2 * 3600 + 300);
        $this->attempt()->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->artisan('assessment:expire-invitations')->expectsOutput('Expired 0 invitations.')->assertSuccessful();
    }

    // ----- answering and scoring -----

    public function test_the_answers_are_saved_and_scored_when_the_test_is_submitted(): void
    {
        $this->invite($this->skillTest([$this->choice(), $this->multiple(), $this->number(), $this->shortText()]));
        $this->start()->assertCreated();
        [$choice, $multiple, $number, $text] = $this->questionIds();

        $this->answer($choice, ['option_positions' => [1]])->assertNoContent();
        $this->answer($multiple, ['option_positions' => [1]])->assertNoContent();
        $this->answer($number, ['number' => 42])->assertNoContent();
        $this->answer($text, ['text' => '  jakarta '])->assertNoContent();

        // The saved answers come back when the candidate returns, and a later answer replaces the earlier one.
        $this->answer($multiple, ['option_positions' => [2, 1]])->assertNoContent();
        $this->attempt()->assertJsonPath('data.questions.1.answer.option_positions', [1, 2])->assertJsonPath('data.questions.2.answer.number', 42);

        $this->take('POST', 'attempt/submit')->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.auto_submitted', false)
            ->assertJsonPath('data.submitted_at', '2026-10-05T09:00:00+00:00')
            ->assertJsonMissingPath('data.questions');

        $result = $this->storedResult();
        $this->assertSame(['scored', '7.00', '7.00', '100.00', 'excellent', 'none'], [$result['status'], $result['earned_points'], $result['max_points'], $result['percentage'], $result['band'], $result['integrity_tier']]);
        $this->assertSame(['submitted'], DB::table('assessment_invitations')->pluck('status')->all());
        $this->assertNotNull(DB::table('assessment_invitations')->value('closed_at'));
    }

    public function test_wrong_answers_score_zero_and_the_band_follows_the_company_settings(): void
    {
        $this->invite($this->skillTest([$this->choice(), $this->multiple(), $this->number(), $this->shortText()]));
        $this->start()->assertCreated();
        [$choice, $multiple, $number, $text] = $this->questionIds();

        $this->answer($choice, ['option_positions' => [1]])->assertNoContent();
        $this->answer($multiple, ['option_positions' => [1]])->assertNoContent();
        $this->answer($number, ['number' => 42.5])->assertNoContent();
        $this->answer($text, ['text' => 'Bandung'])->assertNoContent();
        $this->overrideSetting('assessment.band_good_from', 25);
        $this->overrideSetting('assessment.band_fair_from', 10);

        $this->take('POST', 'attempt/submit')->assertOk();

        $result = $this->storedResult();
        $this->assertSame(['2.00', '7.00', '28.57', 'good'], [$result['earned_points'], $result['max_points'], $result['percentage'], $result['band']]);
        $this->assertSame([2.0, 0.0, 0.0, 0.0], array_map(fn ($score): float => (float) $score, DB::table('assessment_answers')->orderBy('id')->pluck('score')->all()));
    }

    public function test_the_default_bands_follow_the_percentage(): void
    {
        $this->invite($this->skillTest([$this->choice(), $this->number()]));
        $this->start()->assertCreated();
        [, $number] = $this->questionIds();

        $this->answer($number, ['number' => 42])->assertNoContent();
        $this->take('POST', 'attempt/submit')->assertOk();

        // 1 of 3 points is 33.33 percent.
        $this->assertSame(['33.33', 'low'], [$this->storedResult()['percentage'], $this->storedResult()['band']]);
    }

    public function test_an_answer_that_does_not_fit_the_question_is_refused(): void
    {
        $this->invite($this->skillTest([$this->choice(), $this->multiple(), $this->number(), $this->shortText()]));
        $this->start()->assertCreated();
        [$choice, $multiple, $number, $text] = $this->questionIds();

        $this->answer($choice, ['option_positions' => [1, 2]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer')->assertJsonStructure(['errors' => ['option_positions']]);
        $this->answer($choice, ['option_positions' => [4]])->assertStatus(422)->assertJsonStructure(['errors' => ['option_positions']]);
        $this->answer($choice, ['number' => 7])->assertStatus(422)->assertJsonStructure(['errors' => ['option_positions']]);
        $this->answer($multiple, [])->assertStatus(422)->assertJsonStructure(['errors' => ['option_positions']]);
        $this->answer($number, ['text' => 'forty two'])->assertStatus(422)->assertJsonStructure(['errors' => ['number']]);
        $this->answer($text, ['option_positions' => [1]])->assertStatus(422)->assertJsonStructure(['errors' => ['text']]);
        $this->answer($text, ['text' => '   '])->assertStatus(422)->assertJsonStructure(['errors' => ['text']]);
        $this->answer($text, ['text' => 'a', 'number' => 3])->assertStatus(422)->assertJsonStructure(['errors' => ['text']]);
        $this->answer(999999, ['text' => 'x'])->assertNotFound();
        $this->assertSame(0, DB::table('assessment_answers')->count());
    }

    public function test_an_answer_can_be_cleared(): void
    {
        $this->invite($this->skillTest([$this->choice()]));
        $this->start()->assertCreated();
        [$choice] = $this->questionIds();

        $this->answer($choice, ['option_positions' => [1]])->assertNoContent();
        $this->take('DELETE', "attempt/questions/{$choice}/answer")->assertNoContent();

        $this->assertSame(0, DB::table('assessment_answers')->count());
        $this->attempt()->assertJsonPath('data.questions.0.answer', null);
    }

    public function test_a_long_text_answer_leaves_the_result_waiting_for_its_assessor(): void
    {
        $this->invite($this->skillTest([$this->choice(), $this->longText()]), ['assessor_ids' => [$this->adminId]]);
        $this->start()->assertCreated();
        [$choice, $long] = $this->questionIds();

        $this->answer($choice, ['option_positions' => [1]])->assertNoContent();
        $this->answer($long, ['text' => 'I built a job portal.'])->assertNoContent();
        $this->take('POST', 'attempt/submit')->assertOk();

        $result = $this->storedResult();
        $this->assertSame(['pending_scoring', null, null], [$result['status'], $result['percentage'], $result['band']]);
        $this->assertSame('2.00', $result['earned_points']);
        $this->assertSame('7.00', $result['max_points']);
    }

    public function test_an_unanswered_long_text_question_scores_zero_without_waiting(): void
    {
        $this->invite($this->skillTest([$this->choice(), $this->longText()]), ['assessor_ids' => [$this->adminId]]);
        $this->start()->assertCreated();
        [$choice] = $this->questionIds();

        $this->answer($choice, ['option_positions' => [1]])->assertNoContent();
        $this->take('POST', 'attempt/submit')->assertOk();

        $this->assertSame(['scored', '28.57'], [$this->storedResult()['status'], $this->storedResult()['percentage']]);
    }

    // ----- navigation -----

    public function test_a_section_that_cannot_go_back_or_skip_shows_one_question_at_a_time(): void
    {
        $this->invite($this->skillTest([$this->choice('One'), $this->choice('Two'), $this->choice('Three')], ['allow_skip' => false, 'allow_back' => false, 'allow_jump' => false]));
        $this->start()->assertCreated()->assertJsonCount(1, 'data.questions')->assertJsonPath('data.questions.0.index', 0)->assertJsonPath('data.section.allow_back', false);
        $ids = array_map('intval', DB::table('assessment_attempt_questions')->orderBy('position')->pluck('question_id')->all());

        // Only the open question can be answered, and the next one only opens after an answer.
        $this->answer($ids[1], ['option_positions' => [1]])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:question-locked');
        $this->take('PUT', 'attempt/cursor', ['index' => 1])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-navigation');
        $this->take('PUT', 'attempt/cursor', ['index' => 2])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-navigation');

        $this->answer($ids[0], ['option_positions' => [2]])->assertNoContent();
        $this->take('PUT', 'attempt/cursor', ['index' => 1])->assertOk()->assertJsonCount(1, 'data.questions')->assertJsonPath('data.questions.0.index', 1)->assertJsonPath('data.section.current_index', 1);

        // The first question is closed for good.
        $this->take('PUT', 'attempt/cursor', ['index' => 0])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-navigation');
        $this->answer($ids[0], ['option_positions' => [1]])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:question-locked');
        $this->take('DELETE', "attempt/questions/{$ids[0]}/answer")->assertStatus(409);
    }

    public function test_a_section_that_can_go_back_but_not_jump_shows_the_questions_reached(): void
    {
        $this->invite($this->skillTest([$this->choice('One'), $this->choice('Two'), $this->choice('Three')], ['allow_skip' => true, 'allow_back' => true, 'allow_jump' => false]));
        $this->start()->assertCreated();
        $ids = array_map('intval', DB::table('assessment_attempt_questions')->orderBy('position')->pluck('question_id')->all());

        $this->take('PUT', 'attempt/cursor', ['index' => 2])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-navigation');
        $this->take('PUT', 'attempt/cursor', ['index' => 1])->assertOk()->assertJsonCount(2, 'data.questions');
        $this->answer($ids[0], ['option_positions' => [1]])->assertNoContent();
        $this->take('PUT', 'attempt/cursor', ['index' => 0])->assertOk()->assertJsonCount(1, 'data.questions');
        $this->take('PUT', 'attempt/cursor', ['index' => 9])->assertStatus(422);
        $this->take('PUT', 'attempt/cursor', ['index' => 0])->assertStatus(422);
    }

    public function test_a_section_that_can_jump_shows_every_question_and_takes_answers_in_any_order(): void
    {
        $this->invite($this->skillTest([$this->choice('One'), $this->choice('Two'), $this->choice('Three')]));
        $this->start()->assertCreated()->assertJsonCount(3, 'data.questions');
        [$one, , $three] = $this->questionIds();

        $this->answer($three, ['option_positions' => [1]])->assertNoContent();
        $this->answer($one, ['option_positions' => [1]])->assertNoContent();
        $this->take('PUT', 'attempt/cursor', ['index' => 2])->assertOk()->assertJsonPath('data.section.current_index', 2);
    }

    public function test_a_section_that_draws_asks_the_same_questions_every_time(): void
    {
        $questions = array_map(fn (int $number): array => $this->choice("Question {$number}"), range(1, 6));
        $this->invite($this->skillTest($questions, ['draw_count' => 3]));

        $first = $this->start()->assertCreated()->assertJsonPath('data.section.question_count', 3)->assertJsonCount(3, 'data.questions')->json('data.questions');
        $this->assertSame($first, $this->attempt()->json('data.questions'));
        $this->assertSame(3, DB::table('assessment_attempt_questions')->count());

        $bank = array_map('intval', DB::table('assessment_questions')->orderBy('position')->pluck('id')->all());
        $drawn = array_column($first, 'id');
        $this->assertSame($drawn, array_values(array_filter($bank, fn (int $id): bool => in_array($id, $drawn, true))));
    }

    // ----- the clock -----

    /** @return array{0: int, 1: int} the two section ids */
    private function twoSectionTest(): array
    {
        $id = $this->newTest('company', ['title' => 'Two parts']);
        $sections = [];

        foreach (['Part one', 'Part two'] as $title) {
            $sections[] = $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section($title, 60))->assertCreated()->json('data.id');
            $this->company('POST', "sections/{$sectionId}/questions", $this->choice($title.' question'))->assertCreated();
        }

        $this->company('POST', "tests/{$id}/publish")->assertOk();
        $this->invite($id);

        return [$sections[0], $sections[1]];
    }

    public function test_a_section_closes_at_its_deadline_and_the_next_one_opens_then(): void
    {
        $this->twoSectionTest();
        $first = $this->start()->assertCreated()->assertJsonPath('data.ends_at', '2026-10-05T09:02:00+00:00')->assertJsonPath('data.section.ends_at', '2026-10-05T09:01:00+00:00')->json('data.questions.0.id');

        $this->moveClock(30);
        $this->attempt()->assertJsonPath('data.section.position', 1);

        $this->moveClock(61);
        $this->attempt()->assertOk()
            ->assertJsonPath('data.section.position', 2)
            ->assertJsonPath('data.section.title', 'Part two')
            ->assertJsonPath('data.section.opened_at', '2026-10-05T09:01:00+00:00')
            ->assertJsonPath('data.section.ends_at', '2026-10-05T09:02:00+00:00');

        // The first section is closed: its question is no longer reachable.
        $this->answer((int) $first, ['option_positions' => [1]])->assertNotFound();
    }

    public function test_the_candidate_can_finish_a_section_early_and_the_end_moves_up(): void
    {
        $this->twoSectionTest();
        $this->start()->assertCreated();

        $this->moveClock(10);
        $this->take('POST', 'attempt/next-section')->assertOk()
            ->assertJsonPath('data.section.position', 2)
            ->assertJsonPath('data.section.opened_at', '2026-10-05T09:00:10+00:00')
            ->assertJsonPath('data.ends_at', '2026-10-05T09:01:10+00:00');

        $this->take('POST', 'attempt/next-section')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:last-section');
    }

    public function test_when_the_time_runs_out_the_server_submits_what_was_saved(): void
    {
        $this->twoSectionTest();
        $this->start()->assertCreated();
        [$question] = $this->questionIds();
        $this->answer($question, ['option_positions' => [1]])->assertNoContent();

        $this->moveClock(125);

        // A write meets the closed attempt and the submission is kept.
        $this->answer($question, ['option_positions' => [2]])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-closed');
        $this->take('POST', 'attempt/submit')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-closed');

        $this->attempt()->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.auto_submitted', true)->assertJsonPath('data.submitted_at', '2026-10-05T09:02:00+00:00');
        $this->assertSame(['submitted'], DB::table('assessment_invitations')->pluck('status')->all());
        $this->assertSame('2.00', $this->storedResult()['earned_points']);
        $this->assertSame(['assessment.submitted'], $this->notificationTypes($this->recruiterId));
    }

    public function test_the_command_submits_the_attempts_nobody_came_back_to(): void
    {
        $this->twoSectionTest();
        $this->start()->assertCreated();

        $this->artisan('assessment:auto-submit-attempts')->expectsOutput('Submitted 0 attempts.')->assertSuccessful();

        $this->moveClock(121);
        $this->artisan('assessment:auto-submit-attempts')->expectsOutput('Submitted 1 attempt.')->assertSuccessful();
        $this->artisan('assessment:auto-submit-attempts')->expectsOutput('Submitted 0 attempts.')->assertSuccessful();

        $this->assertSame(1, DB::table('assessment_attempts')->where('auto_submitted', true)->count());
        $this->assertSame(['submitted'], DB::table('assessment_invitations')->pluck('status')->all());
        $this->assertSame(1, DB::table('assessment_results')->count());
        $this->assertSame(['assessment.submitted'], $this->notificationTypes($this->recruiterId));
    }

    public function test_a_cancelled_test_closes_the_attempt_and_is_never_submitted(): void
    {
        $this->twoSectionTest();
        $this->start()->assertCreated();
        [$question] = $this->questionIds();

        $this->company('POST', "invitations/{$this->invitationId}/cancel", [], $this->recruiterToken)->assertOk();

        $this->attempt()->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonMissingPath('data.section');
        $this->answer($question, ['option_positions' => [1]])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-closed');

        $this->moveClock(300);
        $this->artisan('assessment:auto-submit-attempts')->expectsOutput('Submitted 0 attempts.')->assertSuccessful();
        $this->assertSame(0, DB::table('assessment_results')->count());
    }

    public function test_a_submitted_test_is_locked_and_tells_the_team_once(): void
    {
        $this->invite($this->skillTest([$this->choice()], title: 'Short'), ['assessor_ids' => [$this->adminId]]);
        $this->start()->assertCreated();
        [$question] = $this->questionIds();
        $this->answer($question, ['option_positions' => [1]])->assertNoContent();

        $this->take('POST', 'attempt/submit')->assertOk();
        $this->take('POST', 'attempt/submit')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-closed');
        $this->answer($question, ['option_positions' => [2]])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-closed');
        $this->start()->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->assertSame(['assessment.submitted'], $this->notificationTypes($this->recruiterId));
        $this->assertSame(['assessment.submitted'], $this->notificationTypes($this->adminId));
        $this->assertSame(['assessment.invited'], $this->notificationTypes($this->seekerId));
        $this->assertSame(1, DB::table('assessment_results')->count());
    }

    public function test_another_candidate_does_not_reach_the_attempt(): void
    {
        $this->invite($this->skillTest([$this->choice()]));
        $this->start()->assertCreated();
        $other = $this->tokenFor($this->seeker('rina.lain@example.test'));

        $this->take('GET', 'attempt', [], $other)->assertNotFound();
        $this->take('POST', 'attempt/submit', [], $other)->assertNotFound();
        $this->take('GET', 'attempt', [], $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
    }

    // ----- integrity -----

    public function test_the_browser_signals_set_the_integrity_tier(): void
    {
        $this->invite($this->skillTest([$this->choice()]));
        $this->start()->assertCreated();

        $this->take('POST', 'attempt/integrity-events', ['type' => 'ip_change'])->assertStatus(422);
        $this->take('POST', 'attempt/integrity-events', ['type' => 'screenshot'])->assertStatus(422);

        foreach (['tab_switch', 'fullscreen_exit', 'paste', 'tab_switch'] as $type) {
            $this->take('POST', 'attempt/integrity-events', ['type' => $type])->assertNoContent();
        }

        $this->take('POST', 'attempt/submit')->assertOk();
        $this->assertSame('some', $this->storedResult()['integrity_tier']);
        $this->assertSame(4, DB::table('assessment_integrity_events')->count());
    }

    public function test_five_signals_are_a_major_tier(): void
    {
        $this->invite($this->skillTest([$this->choice()]));
        $this->start()->assertCreated();

        foreach (range(1, 5) as $ignored) {
            $this->take('POST', 'attempt/integrity-events', ['type' => 'tab_switch'])->assertNoContent();
        }

        $this->take('POST', 'attempt/submit')->assertOk();
        $this->assertSame('major', $this->storedResult()['integrity_tier']);
    }

    public function test_the_server_notes_a_change_of_ip_address(): void
    {
        $this->invite($this->skillTest([$this->choice()]));
        $this->start()->assertCreated();
        $this->assertSame(0, DB::table('assessment_integrity_events')->count());

        $this->attempt()->assertOk();
        $this->assertSame(0, DB::table('assessment_integrity_events')->count());

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
        $this->attempt()->assertOk();
        $this->attempt()->assertOk();

        $this->assertSame(['ip_change'], DB::table('assessment_integrity_events')->pluck('type')->all());
        $this->assertSame('203.0.113.9', DB::table('assessment_attempts')->value('last_ip'));
    }

    public function test_the_browser_reports_an_idle_spell_and_gets_the_idle_limit(): void
    {
        $this->invite($this->skillTest([$this->choice()]));
        $this->start()->assertCreated()->assertJsonPath('data.idle_minutes', 3);

        $this->take('POST', 'attempt/integrity-events', ['type' => 'idle'])->assertNoContent();
        $this->take('POST', 'attempt/integrity-events', ['type' => 'device_switch'])->assertStatus(422);

        $this->assertSame(['idle'], DB::table('assessment_integrity_events')->pluck('type')->all());
        $this->attempt()->assertOk()->assertJsonPath('data.idle_minutes', 3);

        $this->app->make(SettingsStore::class)->setGlobal('assessment.idle_minutes', 10, null);
        $this->app->make('cache')->flush();
        $this->attempt()->assertOk()->assertJsonPath('data.idle_minutes', 10);
    }

    public function test_every_call_after_the_start_carries_the_attempt_session(): void
    {
        $this->invite($this->skillTest([$this->choice()]));
        $token = (string) $this->start()->assertCreated()->json('data.attempt_session');
        $this->assertNotSame('', $token);
        $this->assertSame(hash('sha256', $token), DB::table('assessment_attempts')->value('session_hash'));

        $this->attemptSession = null;
        $this->attempt()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-session-required');
        $this->take('POST', 'attempt/submit')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-session-required');

        $this->attemptSession = 'not-the-token';
        $this->attempt()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-moved');

        $this->attemptSession = $token;
        $this->attempt()->assertOk()->assertJsonMissingPath('data.attempt_session');
    }

    public function test_starting_again_from_another_device_takes_the_attempt_over(): void
    {
        $this->invite($this->skillTest([$this->choice()]));
        $first = (string) $this->start()->assertCreated()->json('data.attempt_session');
        $choice = $this->questionIds()[0];
        $this->answer($choice, ['option_positions' => [1]])->assertNoContent();

        $this->moveClock(120);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
        $second = (string) $this->start()->assertOk()->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.questions.0.answer.option_positions', [1])->json('data.attempt_session');
        $this->assertNotSame($first, $second);
        $this->assertSame(1, DB::table('assessment_attempts')->count());
        $this->assertSame('2026-10-05T09:10:00+00:00', $this->attempt()->json('data.ends_at'), 'The clock never stops.');

        $this->attemptSession = $first;
        $this->attempt()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-moved');
        $this->answer($choice, ['option_positions' => [2]])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-moved');

        $this->assertEqualsCanonicalizing(['device_switch', 'ip_change'], DB::table('assessment_integrity_events')->pluck('type')->all());

        $this->attemptSession = $second;
        $this->take('POST', 'attempt/submit')->assertOk();
        $this->start()->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonMissingPath('data.attempt_session');
    }

    // ----- a psychological battery -----

    public function test_a_battery_is_scored_by_scale_and_takes_the_columns_and_the_drawing(): void
    {
        $battery = $this->battery();
        $this->invite($battery);
        $this->start()->assertCreated()->assertJsonPath('data.section.title', 'Statements')->assertJsonPath('data.section.total', 3);
        [$likert, $forced] = $this->questionIds();

        $this->answer($likert, ['option_positions' => [3]])->assertNoContent();
        $this->answer($forced, ['option_positions' => [2]])->assertNoContent();
        $this->take('POST', 'attempt/next-section')->assertOk()->assertJsonPath('data.section.format', 'speed_columns')->assertJsonMissingPath('data.questions');

        // Two columns of 30 seconds. The server gives the digits of the open column only.
        $speed = $this->attempt()->assertJsonPath('data.speed.column', 1)->assertJsonPath('data.speed.columns', 2)->assertJsonCount(10, 'data.speed.digits')->json('data.speed');
        $sectionId = (int) DB::table('assessment_sections')->where('format', 'speed_columns')->value('id');
        $seed = (int) DB::table('assessment_attempts')->value('seed');
        $this->assertSame(ColumnDigits::of($seed, $sectionId, 1, 10), $speed['digits']);

        $sums = ColumnDigits::expected($speed['digits']);
        $this->take('PUT', 'attempt/columns/2', ['answers' => $sums])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-column');
        $this->take('PUT', 'attempt/columns/1', ['answers' => [...$sums, 1]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-column');
        $this->take('PUT', 'attempt/columns/1', ['answers' => [10]])->assertStatus(422);

        $wrong = $sums;
        $wrong[0] = ($wrong[0] + 1) % 10;
        $this->take('PUT', 'attempt/columns/1', ['answers' => $wrong])->assertNoContent();
        $this->take('PUT', 'attempt/columns/1', ['answers' => $sums])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:column-already-submitted');

        $this->moveClock(31);
        $second = $this->attempt()->assertJsonPath('data.speed.column', 2)->assertJsonPath('data.speed.submitted_columns', [1])->json('data.speed');
        $this->assertNotSame($speed['digits'], $second['digits']);
        $this->take('PUT', 'attempt/columns/2', ['answers' => [ColumnDigits::expected($second['digits'])[0], null, null]])->assertNoContent();

        $this->assertSame([[1, 9, 8], [2, 1, 1]], array_map(fn ($row): array => [(int) $row->column_no, (int) $row->attempted, (int) $row->correct], DB::table('assessment_speed_column_results')->orderBy('column_no')->get()->all()));

        // The drawing section.
        $this->take('POST', 'attempt/next-section')->assertOk()->assertJsonPath('data.section.format', 'drawing')->assertJsonCount(1, 'data.questions');
        $question = (int) $this->attempt()->json('data.questions.0.id');
        $this->assertStringNotContainsString('score', json_encode($this->attempt()->json(), JSON_THROW_ON_ERROR));

        $this->sendFile("attempt/questions/{$question}/drawing", $this->png())->assertNoContent();
        $this->attempt()->assertJsonPath('data.questions.0.drawing.source', 'canvas');
        $files = Storage::allFiles('assessment/drawings');
        $this->assertCount(1, $files);

        $this->sendFile("attempt/questions/{$question}/drawing", $this->png(), ['source' => 'upload'])->assertNoContent();
        $now = Storage::allFiles('assessment/drawings');
        $this->assertCount(1, $now);
        $this->assertNotSame($files, $now);
        $this->assertSame(['upload'], DB::table('assessment_drawings')->pluck('source')->all());

        $this->take('POST', 'attempt/submit')->assertOk();

        $result = $this->storedResult();
        $this->assertSame(['scored', null, null, null], [$result['status'], $result['percentage'], $result['band'], $result['earned_points']]);
        $this->assertEquals(['extraversion' => 3, 'flexibility' => 2, 'order' => 0], json_decode($result['scale_scores'], true));

        $sections = json_decode($result['section_scores'], true);
        $this->assertSame(['questions', 'speed_columns', 'drawing'], array_column($sections, 'format'));
        $this->assertSame([10, 9], [$sections[1]['attempted'], $sections[1]['correct']]);
        $this->assertSame([1, 1], [$sections[2]['answered'], $sections[2]['total']]);
    }

    public function test_a_drawing_must_be_an_image_of_an_allowed_size_for_a_drawing_question(): void
    {
        $battery = $this->battery();
        $this->invite($battery);
        $this->start()->assertCreated();
        $this->take('POST', 'attempt/next-section')->assertOk();
        $this->take('POST', 'attempt/next-section')->assertOk();
        $question = (int) $this->attempt()->json('data.questions.0.id');

        $this->sendFile("attempt/questions/{$question}/drawing", 'not an image')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-image');
        $this->sendFile("attempt/questions/{$question}/drawing", $this->png(), ['source' => 'camera'])->assertStatus(422)->assertJsonStructure(['errors' => ['source']]);
        $this->sendFile('attempt/questions/999999/drawing', $this->png())->assertNotFound();
        $this->assertSame([], Storage::allFiles('assessment/drawings'));

        // A drawing is not an answer.
        $this->answer($question, ['text' => 'a tree'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer');
    }

    public function test_a_drawing_cannot_be_sent_for_a_question_that_is_not_a_drawing_or_to_a_closed_attempt(): void
    {
        $battery = $this->battery();
        $this->invite($battery);
        $this->start()->assertCreated();
        [$likert] = $this->questionIds();

        // The question belongs to the open section but is not a drawing question.
        $this->sendFile("attempt/questions/{$likert}/drawing", $this->png())->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer');

        $this->take('POST', 'attempt/submit')->assertOk();
        $this->sendFile("attempt/questions/{$likert}/drawing", $this->png())->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:attempt-closed');
    }

    public function test_a_speed_section_takes_no_answers_and_a_question_section_takes_no_columns(): void
    {
        $battery = $this->battery();
        $this->invite($battery);
        $this->start()->assertCreated();
        [$likert] = $this->questionIds();

        $this->take('PUT', 'attempt/columns/1', ['answers' => [1]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-column');
        $this->take('POST', 'attempt/next-section')->assertOk();
        $this->answer($likert, ['option_positions' => [1]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer');
        $this->take('PUT', 'attempt/cursor', ['index' => 1])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer');
    }
}
