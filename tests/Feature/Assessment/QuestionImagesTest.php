<?php

namespace Tests\Feature\Assessment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * `docs/design/phase-8-question-images.md`: pictures on a question (several, in order) and on its options (one each), uploaded to the test first and named when the question is saved; which types take them; the lock of a
 * sent test; copies; deletes; the purge of pictures nobody named; and the delivery to the candidate who is taking the test. The disk is faked, so nothing is left behind.
 */
final class QuestionImagesTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $applicationId;

    private int $invitationId = 0;

    private ?string $attemptSession = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    private function png(int $width = 120, int $height = 80): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 20, 20));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** Uploads a picture to the test through the area (`company`, `provider` or `library`) and returns the response. */
    private function upload(int $testId, string $area = 'company', ?string $contents = null, string $alt = 'Figure A of five', string $name = 'figure.png'): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $token = ['company' => $this->adminToken, 'provider' => $this->psychologistToken, 'library' => $this->assessmentAdminToken][$area];
        $uri = ['company' => "/api/v1/company/assessment/tests/{$testId}/images", 'provider' => "/api/v1/provider/tests/{$testId}/images", 'library' => "/api/v1/admin/assessment/library/tests/{$testId}/images"][$area];

        return $this->withToken($token)->post($uri, ['file' => UploadedFile::fake()->createWithContent($name, $contents ?? $this->png()), 'alt' => $alt], ['Accept' => 'application/json']);
    }

    private function pictureId(int $testId, string $alt = 'Figure A of five'): int
    {
        return (int) $this->upload($testId, alt: $alt)->assertCreated()->json('data.id');
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array{0: int, 1: int} the test id and the section id
     */
    private function newTestWithSection(array $section = []): array
    {
        $id = $this->newTest('company');
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section('Basics', 600, $section))->assertCreated()->json('data.id');

        return [$id, $sectionId];
    }

    /** @return array<string, mixed> an odd-one-out question: an instruction and five pictures as the options */
    private function oddOneOut(int $testId, string $prefix = 'Figure'): array
    {
        $options = [];

        foreach (['A', 'B', 'C', 'D', 'E'] as $index => $letter) {
            $options[] = ['label' => $letter, 'is_correct' => $index === 2, 'image_id' => $this->pictureId($testId, "{$prefix} {$letter} of five")];
        }

        return ['type' => 'single_choice', 'text' => 'Choose the picture that differs from the others.', 'points' => 1, 'options' => $options];
    }

    // ----- upload -----

    public function test_a_picture_is_uploaded_to_a_test_and_checked_by_its_content(): void
    {
        [$id] = $this->newTestWithSection();

        $this->upload($id, alt: '  Figure A of five  ')->assertCreated()->assertJsonPath('data.alt', 'Figure A of five')->assertJsonPath('data.width', 120)->assertJsonPath('data.height', 80);
        $this->assertCount(1, Storage::allFiles('assessment/question-images'));

        $this->upload($id, contents: 'not an image')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-image');
        $this->upload($id, alt: '')->assertStatus(422);
        $this->upload($id, alt: str_repeat('a', 301))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-image');
        $this->assertCount(1, Storage::allFiles('assessment/question-images'));
    }

    public function test_the_size_limit_is_a_setting_and_a_large_picture_is_shrunk(): void
    {
        [$id] = $this->newTestWithSection();

        $this->upload($id, contents: $this->png(3200, 2400))->assertCreated()->assertJsonPath('data.width', 1600)->assertJsonPath('data.height', 1200);

        $this->upload($id, contents: str_repeat('a', 2 * 1024 * 1024 + 1))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:file-too-large');
    }

    public function test_a_picture_belongs_to_the_author_of_the_test_and_a_locked_test_takes_none(): void
    {
        [$id] = $this->newTestWithSection();

        $this->upload($id, 'provider')->assertNotFound();
        $this->upload($id, 'library')->assertNotFound();

        $picture = $this->pictureId($id);
        $this->company('GET', "tests/{$id}/images/{$picture}")->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->provider('GET', "tests/{$id}/images/{$picture}")->assertNotFound();

        $sectionId = (int) DB::table('assessment_sections')->where('test_id', $id)->value('id');
        $this->company('POST', "sections/{$sectionId}/questions", $this->choice())->assertCreated();
        $this->company('POST', "tests/{$id}/publish")->assertOk();
        $this->sendTest($id);
        $this->upload($id)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:test-locked');
    }

    // ----- naming the pictures in a question -----

    public function test_a_question_names_its_pictures_in_order_and_each_option_names_one(): void
    {
        [$id, $sectionId] = $this->newTestWithSection();
        $first = $this->pictureId($id, 'The first figure');
        $second = $this->pictureId($id, 'The second figure');
        $body = [...$this->oddOneOut($id), 'image_ids' => [$second, $first]];

        $question = $this->company('POST', "sections/{$sectionId}/questions", $body)->assertCreated()
            ->assertJsonPath('data.images.0.id', $second)->assertJsonPath('data.images.1.id', $first)->assertJsonPath('data.images.1.alt', 'The first figure')
            ->assertJsonPath('data.options.2.label', 'C')->assertJsonPath('data.options.2.image.alt', 'Figure C of five');

        $this->assertSame(7, DB::table('assessment_question_images')->whereNotNull('question_id')->count());
        $this->company('GET', "sections/{$sectionId}/questions")->assertOk()->assertJsonPath('data.0.options.4.image.alt', 'Figure E of five');

        $id2 = (int) $question->json('data.id');
        $changed = [...$body, 'image_ids' => [$first], 'options' => array_map(static fn (array $option): array => [...$option, 'image_id' => null], $body['options'])];
        $this->company('PUT', "questions/{$id2}", $changed)->assertOk()->assertJsonCount(1, 'data.images')->assertJsonPath('data.options.0.image', null);
        $this->assertSame(1, DB::table('assessment_question_images')->whereNotNull('question_id')->count());
        $this->assertCount(1, Storage::allFiles('assessment/question-images'));
    }

    public function test_the_pictures_of_a_question_are_limited_by_a_setting(): void
    {
        [$id, $sectionId] = $this->newTestWithSection();
        $ids = array_map(fn (int $number): int => $this->pictureId($id, "Figure {$number}"), range(1, 5));

        $body = ['type' => 'long_text', 'text' => 'Describe what you see.', 'points' => 3];
        $this->company('POST', "sections/{$sectionId}/questions", [...$body, 'image_ids' => array_slice($ids, 0, 5)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:too-many-images');
        $this->company('POST', "sections/{$sectionId}/questions", [...$body, 'image_ids' => array_slice($ids, 0, 4)])->assertCreated()->assertJsonCount(4, 'data.images');
    }

    public function test_only_the_types_that_may_show_pictures_take_them(): void
    {
        [$id, $sectionId] = $this->newTestWithSection();
        $picture = $this->pictureId($id);

        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'number', 'text' => 'How many dots are there?', 'points' => 1, 'options' => [['label' => '12']], 'image_ids' => [$picture]])->assertCreated();

        $trueFalse = ['type' => 'true_false', 'text' => 'The two figures are equal.', 'points' => 1, 'options' => [['label' => 'True', 'is_correct' => true, 'image_id' => $this->pictureId($id)], ['label' => 'False']]];
        $this->company('POST', "sections/{$sectionId}/questions", $trueFalse)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-question');

        $reused = $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'long_text', 'text' => 'Describe what you see.', 'points' => 3, 'image_ids' => [$picture]]);
        $reused->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-image');

        $other = $this->newTest('company');
        $foreign = $this->pictureId($other);
        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'long_text', 'text' => 'Describe what you see.', 'points' => 3, 'image_ids' => [$foreign]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-image');
        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'long_text', 'text' => 'Describe what you see.', 'points' => 3, 'image_ids' => [999999]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-image');
        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'long_text', 'text' => 'Describe what you see.', 'points' => 3, 'image_ids' => [$foreign, $foreign]])->assertStatus(422);
    }

    public function test_a_statement_question_and_a_drawing_sheet(): void
    {
        $battery = (int) $this->provider('POST', 'tests', ['title' => 'Likert and drawing', 'kind' => 'psychological'])->assertCreated()->json('data.id');
        $likert = (int) $this->provider('POST', "tests/{$battery}/sections", ['title' => 'Statements', 'format' => 'questions', 'duration_seconds' => 600])->assertCreated()->json('data.id');
        $sheetSection = (int) $this->provider('POST', "tests/{$battery}/sections", ['title' => 'Sheet', 'format' => 'drawing', 'duration_seconds' => 900])->assertCreated()->json('data.id');
        $picture = (int) $this->upload($battery, 'provider')->assertCreated()->json('data.id');
        $second = (int) $this->upload($battery, 'provider')->assertCreated()->json('data.id');

        $statement = ['type' => 'likert', 'text' => 'I like to plan ahead.', 'scale_key' => 'plan', 'options' => [['label' => 'Agree', 'score_value' => 2, 'scale_key' => 'plan'], ['label' => 'Disagree', 'score_value' => 0, 'scale_key' => 'plan']]];
        $this->provider('POST', "sections/{$likert}/questions", [...$statement, 'image_ids' => [$picture]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-question');

        $this->provider('POST', "sections/{$sheetSection}/questions", ['type' => 'drawing', 'text' => 'Complete the drawing in each square.', 'points' => 10, 'image_ids' => [$picture, $second]])->assertStatus(422);
        $this->provider('POST', "sections/{$sheetSection}/questions", ['type' => 'drawing', 'text' => 'Complete the drawing in each square.', 'points' => 10, 'image_ids' => [$picture]])->assertCreated()->assertJsonCount(1, 'data.images');
    }

    // ----- copies and deletes -----

    public function test_a_clone_copies_the_pictures_with_files_of_their_own(): void
    {
        [$id, $sectionId] = $this->newTestWithSection();
        $this->company('POST', "sections/{$sectionId}/questions", $this->oddOneOut($id))->assertCreated();
        $this->assertCount(5, Storage::allFiles('assessment/question-images'));

        $copy = (int) $this->company('POST', "tests/{$id}/clone")->assertCreated()->json('data.id');
        $this->assertCount(10, Storage::allFiles('assessment/question-images'));

        $copySection = (int) DB::table('assessment_sections')->where('test_id', $copy)->value('id');
        $this->company('GET', "sections/{$copySection}/questions")->assertOk()->assertJsonPath('data.0.options.3.image.alt', 'Figure D of five');
        $this->assertSame(5, DB::table('assessment_question_images')->where('test_id', $copy)->count());

        $this->company('DELETE', "tests/{$id}")->assertNoContent();
        $this->assertCount(5, Storage::allFiles('assessment/question-images'));
        $this->company('GET', "sections/{$copySection}/questions")->assertOk()->assertJsonPath('data.0.options.0.image.alt', 'Figure A of five');
    }

    public function test_deleting_a_question_or_a_section_deletes_its_files(): void
    {
        [$id, $sectionId] = $this->newTestWithSection();
        $question = (int) $this->company('POST', "sections/{$sectionId}/questions", $this->oddOneOut($id))->assertCreated()->json('data.id');
        $this->assertCount(5, Storage::allFiles('assessment/question-images'));

        $this->company('DELETE', "questions/{$question}")->assertNoContent();
        $this->assertSame([], Storage::allFiles('assessment/question-images'));
        $this->assertSame(0, DB::table('assessment_question_images')->count());

        $this->company('POST', "sections/{$sectionId}/questions", $this->oddOneOut($id))->assertCreated();
        $this->company('DELETE', "sections/{$sectionId}")->assertNoContent();
        $this->assertSame([], Storage::allFiles('assessment/question-images'));
    }

    public function test_a_picture_nobody_named_is_purged_after_a_day(): void
    {
        [$id, $sectionId] = $this->newTestWithSection();
        $named = $this->pictureId($id, 'Named');
        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'long_text', 'text' => 'Describe what you see.', 'points' => 3, 'image_ids' => [$named]])->assertCreated();
        $this->pictureId($id, 'Unused');

        Carbon::setTestNow('2026-10-05 20:00:00');
        Artisan::call('assessment:purge-question-images');
        $this->assertSame(2, DB::table('assessment_question_images')->count());

        Carbon::setTestNow('2026-10-06 10:00:00');
        Artisan::call('assessment:purge-question-images');
        $this->assertSame(1, DB::table('assessment_question_images')->count());
        $this->assertSame('Named', DB::table('assessment_question_images')->value('alt'));
        $this->assertCount(1, Storage::allFiles('assessment/question-images'));
    }

    // ----- the candidate -----

    private function sendTest(int $testId): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => ['test_id' => $testId, 'due_at' => now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z')]], $this->recruiterToken)->assertOk();
        $this->invitationId = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');
    }

    /** @param  array<string, mixed>  $body */
    private function take(string $method, string $uri, array $body = []): TestResponse
    {
        $response = $this->api($method, "/me/assessment/invitations/{$this->invitationId}/{$uri}", $body, $this->seekerToken, $this->attemptSession === null ? [] : ['X-Attempt-Session' => $this->attemptSession]);

        if ($uri === 'start' && is_string($session = $response->json('data.attempt_session'))) {
            $this->attemptSession = $session;
        }

        return $response;
    }

    public function test_the_candidate_sees_the_pictures_of_the_question_on_screen_and_nothing_else(): void
    {
        [$id, $sectionId] = $this->newTestWithSection(['allow_skip' => false, 'allow_back' => false, 'allow_jump' => false]);
        $one = (int) $this->company('POST', "sections/{$sectionId}/questions", $this->oddOneOut($id, 'First'))->assertCreated()->json('data.id');
        $two = (int) $this->company('POST', "sections/{$sectionId}/questions", $this->oddOneOut($id, 'Second'))->assertCreated()->json('data.id');
        $this->company('POST', "tests/{$id}/publish")->assertOk();
        $this->sendTest($id);
        $this->take('POST', 'start', ['consent' => true])->assertCreated();

        $questions = $this->take('GET', 'attempt')->assertOk()->json('data.questions');
        $this->assertCount(1, $questions, 'the section does not let the candidate jump');
        $shown = $questions[0];
        $hidden = (int) $shown['id'] === $one ? $two : $one;
        $this->assertSame([], $shown['images']);
        $this->assertStringEndsWith('C of five', $shown['options'][2]['image']['alt']);
        $this->assertArrayNotHasKey('path', $shown['options'][2]['image']);

        $imageId = (int) $shown['options'][2]['image']['id'];
        $this->take('GET', "attempt/questions/{$shown['id']}/images/{$imageId}")->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');

        $hiddenImage = (int) DB::table('assessment_question_images')->where('question_id', $hidden)->value('id');
        $this->take('GET', "attempt/questions/{$hidden}/images/{$hiddenImage}")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:question-locked');
        $this->take('GET', "attempt/questions/{$shown['id']}/images/{$hiddenImage}")->assertNotFound();
    }

    public function test_another_candidate_does_not_reach_a_picture(): void
    {
        [$id, $sectionId] = $this->newTestWithSection();
        $question = (int) $this->company('POST', "sections/{$sectionId}/questions", $this->oddOneOut($id))->assertCreated()->json('data.id');
        $this->company('POST', "tests/{$id}/publish")->assertOk();
        $this->sendTest($id);
        $this->take('POST', 'start', ['consent' => true])->assertCreated();
        $picture = (int) DB::table('assessment_question_images')->where('question_id', $question)->value('id');

        $this->api('GET', "/me/assessment/invitations/{$this->invitationId}/attempt/questions/{$question}/images/{$picture}", [], $this->tokenFor($this->seeker('wawan@example.test')), ['X-Attempt-Session' => (string) $this->attemptSession])->assertNotFound();
    }
}
