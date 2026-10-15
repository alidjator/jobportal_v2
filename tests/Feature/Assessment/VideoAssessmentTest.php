<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * Tele-interview form 2, part 1: a video section and the video answer question type, the rules a test with video questions follows when it is published
 * and sent, the separate consent to be recorded when the candidate starts it, and declining the test.
 */
final class VideoAssessmentTest extends FeatureTestCase
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

    /** @return array<string, mixed> */
    private function videoSection(string $title = 'Interview', int $seconds = 900, array $extra = []): array
    {
        return ['title' => $title, 'format' => 'video', 'duration_seconds' => $seconds, ...$extra];
    }

    /** @return array<string, mixed> */
    private function videoQuestion(array $extra = []): array
    {
        return ['type' => 'video_answer', 'text' => 'Tell us about a project you are proud of.', 'points' => 10, 'think_seconds' => 60, 'answer_seconds' => 120, 'max_retakes' => 1, ...$extra];
    }

    private function videoSectionOf(int $testId, array $extra = []): int
    {
        return (int) $this->company('POST', "tests/{$testId}/sections", $this->videoSection(extra: $extra))->assertCreated()->json('data.id');
    }

    /** A published skill test with one video section and one video question (think 60 s, answer 120 s, one retake: 300 s needed of 900). */
    private function publishedVideoTest(array $question = []): int
    {
        $id = $this->newTest('company', ['title' => 'Video screening']);
        $sectionId = $this->videoSectionOf($id);
        $this->company('POST', "sections/{$sectionId}/questions", $this->videoQuestion($question))->assertCreated();
        $this->company('POST', "tests/{$id}/publish")->assertOk();

        return $id;
    }

    private function at(Carbon $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** @return array<string, mixed> */
    private function invitationBody(int $testId, array $overrides = []): array
    {
        return ['test_id' => $testId, 'due_at' => $this->at(now()->addDays(3)), 'assessor_ids' => [$this->adminId], ...$overrides];
    }

    /** The first invitation of an application is sent with the move to its assessment stage. */
    private function moveToAssessment(int $testId, array $overrides = []): TestResponse
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');

        return $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => $this->invitationBody($testId, $overrides)], $this->recruiterToken);
    }

    /** A later invitation of an application that is already in its assessment stage. */
    private function send(int $testId, array $overrides = []): TestResponse
    {
        return $this->company('PUT', "applications/{$this->applicationId}/invitation", $this->invitationBody($testId, $overrides), $this->recruiterToken);
    }

    private function invite(int $testId): void
    {
        $this->moveToAssessment($testId)->assertNoContent();
        $this->invitationId = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');
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

    private function show(): TestResponse
    {
        return $this->api('GET', "/me/assessment/invitations/{$this->invitationId}", [], $this->seekerToken);
    }

    /** @return list<string> */
    private function notificationTypes(int $userId): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', 'assessment.%')->pluck('type')->all();
        sort($types);

        return $types;
    }

    // ----- authoring -----

    public function test_a_video_section_is_forward_only_and_holds_video_questions(): void
    {
        $id = $this->newTest();

        $this->company('POST', "tests/{$id}/sections", $this->videoSection(extra: ['allow_back' => true, 'allow_jump' => true, 'allow_skip' => true]))
            ->assertCreated()->assertJsonPath('data.format', 'video')->assertJsonPath('data.allow_back', false)->assertJsonPath('data.allow_jump', false)->assertJsonPath('data.allow_skip', true);

        $sectionId = (int) DB::table('assessment_sections')->where('test_id', $id)->value('id');
        $this->company('POST', "sections/{$sectionId}/questions", $this->choice())->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:video-not-allowed')->assertJsonStructure(['errors' => ['type']]);
        $this->company('POST', "sections/{$sectionId}/questions", $this->videoQuestion())->assertCreated()
            ->assertJsonPath('data.type', 'video_answer')->assertJsonPath('data.think_seconds', 60)->assertJsonPath('data.answer_seconds', 120)->assertJsonPath('data.max_retakes', 1)->assertJsonPath('data.options', []);

        $this->company('POST', "tests/{$id}/sections", $this->videoSection(extra: ['draw_count' => 1]))->assertStatus(422)->assertJsonStructure(['errors' => ['draw_count']]);
    }

    public function test_a_video_question_belongs_to_a_video_section_of_a_skill_test(): void
    {
        $id = $this->newTest();
        $plain = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        $this->company('POST', "sections/{$plain}/questions", $this->videoQuestion())->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:video-not-allowed');

        $battery = (int) $this->provider('POST', 'tests', ['title' => 'Battery', 'measured_capabilities' => ['Logic']])->assertCreated()->json('data.id');
        $this->provider('POST', "tests/{$battery}/sections", $this->videoSection())->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:video-not-allowed')->assertJsonStructure(['errors' => ['format']]);
    }

    public function test_the_video_settings_of_a_question_are_checked(): void
    {
        $id = $this->newTest();
        $sectionId = $this->videoSectionOf($id);
        $post = fn (array $extra) => $this->company('POST', "sections/{$sectionId}/questions", $this->videoQuestion($extra))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-question');

        $post(['answer_seconds' => 29])->assertJsonStructure(['errors' => ['answer_seconds']]);
        $post(['answer_seconds' => 301])->assertJsonStructure(['errors' => ['answer_seconds']]);
        $post(['answer_seconds' => null])->assertJsonStructure(['errors' => ['answer_seconds']]);
        $post(['think_seconds' => 10])->assertJsonStructure(['errors' => ['think_seconds']]);
        $post(['think_seconds' => 301])->assertJsonStructure(['errors' => ['think_seconds']]);
        $post(['max_retakes' => 6])->assertJsonStructure(['errors' => ['max_retakes']]);
        $post(['max_retakes' => null])->assertJsonStructure(['errors' => ['max_retakes']]);
        $post(['options' => [['label' => 'a'], ['label' => 'b']]])->assertJsonStructure(['errors' => ['options']]);
        $post(['points' => 0])->assertJsonStructure(['errors' => ['points']]);

        $this->company('POST', "sections/{$sectionId}/questions", $this->videoQuestion(['think_seconds' => null, 'max_retakes' => 0]))
            ->assertCreated()->assertJsonPath('data.think_seconds', null)->assertJsonPath('data.max_retakes', 0);
        $this->company('POST', "sections/{$sectionId}/questions", $this->videoQuestion(['think_seconds' => 30, 'answer_seconds' => 30, 'max_retakes' => 5]))->assertCreated();
        $this->company('POST', "sections/{$sectionId}/questions", $this->videoQuestion(['think_seconds' => 300, 'answer_seconds' => 300]))->assertCreated();
    }

    public function test_only_a_video_question_has_video_settings(): void
    {
        $id = $this->newTest();
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        foreach (['think_seconds' => 60, 'answer_seconds' => 60, 'max_retakes' => 1] as $field => $value) {
            $this->company('POST', "sections/{$sectionId}/questions", $this->choice('Which is a prime number?', [$field => $value]))->assertStatus(422)->assertJsonStructure(['errors' => [$field]]);
        }

        $this->company('POST', "sections/{$sectionId}/questions", $this->choice())->assertCreated()->assertJsonPath('data.think_seconds', null)->assertJsonPath('data.answer_seconds', null)->assertJsonPath('data.max_retakes', null);
    }

    public function test_a_video_question_is_changed_and_cloned_with_its_settings(): void
    {
        $id = $this->newTest();
        $sectionId = $this->videoSectionOf($id);
        $questionId = (int) $this->company('POST', "sections/{$sectionId}/questions", $this->videoQuestion())->assertCreated()->json('data.id');

        $this->company('PUT', "questions/{$questionId}", $this->videoQuestion(['think_seconds' => null, 'answer_seconds' => 90, 'max_retakes' => 3]))
            ->assertOk()->assertJsonPath('data.think_seconds', null)->assertJsonPath('data.answer_seconds', 90)->assertJsonPath('data.max_retakes', 3);

        $copy = (int) $this->company('POST', "tests/{$id}/clone")->assertCreated()->json('data.id');
        $copySection = (int) DB::table('assessment_sections')->where('test_id', $copy)->value('id');
        $this->company('GET', "sections/{$copySection}/questions")->assertOk()->assertJsonPath('data.0.type', 'video_answer')->assertJsonPath('data.0.answer_seconds', 90)->assertJsonPath('data.0.max_retakes', 3);
    }

    public function test_publishing_checks_that_the_section_time_covers_the_video_questions(): void
    {
        $id = $this->newTest();
        $sectionId = $this->videoSectionOf($id, ['duration_seconds' => 240]);
        $this->company('POST', "sections/{$sectionId}/questions", $this->videoQuestion())->assertCreated();

        // 60 s think + 120 s × 2 takes = 300 s, but the section lasts 240 s.
        $this->company('POST', "tests/{$id}/publish")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:test-incomplete')->assertJsonPath('errors.test.0', fn (string $message): bool => str_contains($message, 'at least 300 seconds'));

        $this->company('PUT', "sections/{$sectionId}", $this->videoSection(seconds: 300))->assertOk();
        $this->company('POST', "tests/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'published');
    }

    public function test_an_unlimited_think_time_counts_for_nothing_in_the_section_time(): void
    {
        $id = $this->newTest();
        $sectionId = $this->videoSectionOf($id, ['duration_seconds' => 120]);
        $this->company('POST', "sections/{$sectionId}/questions", $this->videoQuestion(['think_seconds' => null, 'max_retakes' => 0]))->assertCreated();

        $this->company('POST', "tests/{$id}/publish")->assertOk();
    }

    // ----- sending -----

    public function test_a_test_with_video_questions_needs_an_assessor(): void
    {
        $id = $this->publishedVideoTest();

        $this->moveToAssessment($id, ['assessor_ids' => []])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-assessor');
        $this->assertSame(0, DB::table('assessment_invitations')->count());

        $this->moveToAssessment($id)->assertNoContent();
    }

    // ----- taking -----

    public function test_the_candidate_sees_that_the_test_records_video(): void
    {
        $this->invite($this->publishedVideoTest());

        $this->show()->assertOk()->assertJsonPath('data.video_consent_required', true)->assertJsonPath('data.sections.0.format', 'video');
    }

    public function test_a_test_with_video_questions_needs_the_separate_recording_consent_to_start(): void
    {
        $this->invite($this->publishedVideoTest());

        $this->take('POST', 'start', ['consent' => true])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:video-consent-required')->assertJsonStructure(['errors' => ['video_consent']]);
        $this->take('POST', 'start', ['consent' => true, 'video_consent' => false])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:video-consent-required');
        $this->assertSame(0, DB::table('assessment_attempts')->count());
        $this->assertSame(['invited'], DB::table('assessment_invitations')->pluck('status')->all());

        $this->take('POST', 'start', ['video_consent' => true])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:consent-required');

        $this->take('POST', 'start', ['consent' => true, 'video_consent' => true])->assertCreated()->assertJsonPath('data.section.format', 'video');
        $this->assertNotNull(DB::table('assessment_attempts')->value('video_consented_at'));
        $this->assertSame(['started'], DB::table('assessment_invitations')->pluck('status')->all());

        // Starting again changes nothing, with or without the consent.
        $this->take('POST', 'start', ['consent' => true])->assertOk();
    }

    public function test_a_test_without_video_questions_needs_no_recording_consent(): void
    {
        $id = $this->newTest();
        $this->sectionWithQuestion('company', $id);
        $this->company('POST', "tests/{$id}/publish")->assertOk();
        $this->invite($id);

        $this->show()->assertOk()->assertJsonPath('data.video_consent_required', false);
        $this->take('POST', 'start', ['consent' => true])->assertCreated();
        $this->assertNull(DB::table('assessment_attempts')->value('video_consented_at'));
    }

    public function test_a_video_answer_is_recorded_not_typed(): void
    {
        $this->invite($this->publishedVideoTest());
        $this->take('POST', 'start', ['consent' => true, 'video_consent' => true])->assertCreated();
        $questionId = (int) DB::table('assessment_questions')->value('id');

        $this->take('GET', 'attempt')->assertOk()->assertJsonPath('data.questions.0.type', 'video_answer')->assertJsonPath('data.questions.0.video.answer_seconds', 120)->assertJsonPath('data.questions.0.video.max_retakes', 1);
        $this->take('PUT', "attempt/questions/{$questionId}/answer", ['text' => 'typed answer'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer');
    }

    // ----- declining -----

    public function test_the_candidate_declines_the_test_and_the_team_is_told(): void
    {
        $this->invite($this->publishedVideoTest());

        $this->take('POST', 'decline')->assertNoContent();

        $row = DB::table('assessment_invitations')->first();
        $this->assertSame('declined', $row->status);
        $this->assertNotNull($row->closed_at);
        $this->assertSame(['assessment.declined'], $this->notificationTypes($this->recruiterId));
        $this->assertSame(['assessment.declined'], $this->notificationTypes($this->adminId));

        $this->show()->assertOk()->assertJsonPath('data.status', 'declined');
        $this->take('POST', 'start', ['consent' => true, 'video_consent' => true])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:invitation-not-open');
        $this->take('POST', 'decline')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:invitation-not-open');
    }

    public function test_a_declined_test_frees_the_application_for_another_invitation(): void
    {
        $first = $this->publishedVideoTest();
        $this->invite($first);
        $this->take('POST', 'decline')->assertNoContent();

        $this->send($first)->assertSuccessful();
        $this->assertSame(['declined', 'invited'], DB::table('assessment_invitations')->orderBy('id')->pluck('status')->all());
    }

    public function test_a_started_test_cannot_be_declined(): void
    {
        $this->invite($this->publishedVideoTest());
        $this->take('POST', 'start', ['consent' => true, 'video_consent' => true])->assertCreated();

        $this->take('POST', 'decline')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:invitation-not-open');
    }

    public function test_only_the_candidate_declines_their_own_test(): void
    {
        $this->invite($this->publishedVideoTest());

        $this->take('POST', 'decline', token: $this->recruiterToken)->assertForbidden();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->json('POST', "/api/v1/me/assessment/invitations/{$this->invitationId}/decline")->assertUnauthorized();
        $this->assertSame(['invited'], DB::table('assessment_invitations')->pluck('status')->all());
    }
}
