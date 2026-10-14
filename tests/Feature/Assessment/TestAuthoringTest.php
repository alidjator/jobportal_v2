<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/** Phase 6, part 3: a company admin writes the company's skill tests; an admin writes the system library with the same endpoints. */
final class TestAuthoringTest extends FeatureTestCase
{
    use BuildsAssessmentFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildAssessmentFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    // ----- access -----

    public function test_every_authoring_route_needs_a_signed_in_account(): void
    {
        foreach ([['GET', '/company/assessment/tests'], ['POST', '/company/assessment/tests'], ['GET', '/company/assessment/library'], ['GET', '/provider/tests'], ['GET', '/admin/assessment/library/tests']] as [$method, $uri]) {
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
            $this->json($method, '/api/v1'.$uri)->assertStatus(401);
        }
    }

    public function test_only_a_company_admin_writes_tests_but_every_member_reads_the_catalogue(): void
    {
        $this->company('POST', 'tests', ['title' => 'Logic and numbers'], $this->recruiterToken)
            ->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:company-permission-required');
        $this->company('GET', 'tests', [], $this->recruiterToken)->assertForbidden();
        $this->company('GET', 'library', [], $this->recruiterToken)->assertOk();
    }

    public function test_the_library_needs_its_own_permission(): void
    {
        $admin = AdminModel::query()->create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['assessment_providers.manage']);
        $token = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;

        $this->library('GET', 'tests', [], $token)->assertForbidden();
        $this->library('GET', 'tests')->assertOk();
    }

    public function test_a_company_never_sees_the_tests_of_another_company(): void
    {
        $id = $this->newTest();

        $this->company('GET', "tests/{$id}", [], $this->otherAdminToken)->assertNotFound();
        $this->company('PUT', "tests/{$id}", ['title' => 'Stolen title'], $this->otherAdminToken)->assertNotFound();
        $this->company('DELETE', "tests/{$id}", [], $this->otherAdminToken)->assertNotFound();
        $this->assertSame([], $this->company('GET', 'tests', [], $this->otherAdminToken)->json('data'));
    }

    public function test_a_suspended_company_cannot_write_tests(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->company('POST', 'tests', ['title' => 'Logic and numbers'])->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
    }

    // ----- tests -----

    public function test_a_company_admin_creates_a_draft_skill_test_and_lists_it(): void
    {
        $this->company('POST', 'tests', [
            'title' => '  Logic and numbers ', 'description' => 'Reasoning with numbers', 'instructions' => 'Answer all questions.', 'measured_capabilities' => ['Logic', ' Arithmetic '],
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'skill')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'Logic and numbers')
            ->assertJsonPath('data.measured_capabilities', ['Logic', 'Arithmetic']);

        $row = DB::table('assessment_tests')->first();
        $this->assertSame($this->companyId, (int) $row->company_id);
        $this->assertSame($this->adminId, (int) $row->created_by_user_id);
        $this->assertNull($row->created_by_admin_id);

        $this->company('GET', 'tests')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Logic and numbers');
        $this->company('GET', 'tests?filter[status]=published')->assertOk()->assertJsonPath('meta.total', 0);
        $this->company('GET', 'tests?filter[q]=numbers')->assertOk()->assertJsonPath('meta.total', 1);
        $this->company('GET', 'tests?filter[other]=1')->assertStatus(422);
    }

    public function test_the_descriptive_part_of_a_test_is_checked(): void
    {
        $this->company('POST', 'tests', ['title' => 'ab'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-test')->assertJsonStructure(['errors' => ['title']]);
        $this->company('POST', 'tests', ['title' => 'Valid title', 'measured_capabilities' => array_map(static fn (int $i): string => "Skill {$i}", range(1, 11))])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['measured_capabilities']]);
        $this->company('POST', 'tests', ['title' => 'Valid title', 'measured_capabilities' => ['Logic', 'logic']])->assertStatus(422)->assertJsonStructure(['errors' => ['measured_capabilities']]);
        $this->company('POST', 'tests', ['title' => 'Valid title', 'description' => str_repeat('x', 5001)])->assertStatus(422)->assertJsonStructure(['errors' => ['description']]);
    }

    public function test_a_test_is_changed_and_an_archived_one_is_not(): void
    {
        $id = $this->newTest();

        $this->company('PUT', "tests/{$id}", ['title' => 'Renamed test', 'description' => ''])->assertOk()->assertJsonPath('data.title', 'Renamed test')->assertJsonPath('data.description', null);

        $this->company('POST', "tests/{$id}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
        $this->company('PUT', "tests/{$id}", ['title' => 'Changed again'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:test-archived');
        $this->company('POST', "tests/{$id}/sections", $this->section())->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:test-archived');
    }

    public function test_the_company_limit_counts_live_tests_only(): void
    {
        $this->overrideSetting('assessment.max_company_tests', 2);

        $first = $this->newTest();
        $this->newTest();
        $this->company('POST', 'tests', ['title' => 'A third test'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:test-limit-reached');

        $this->company('POST', "tests/{$first}/archive")->assertOk();
        $third = $this->newTest();

        $this->company('POST', "tests/{$first}/restore")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:test-limit-reached');
        $this->company('POST', "tests/{$third}/clone")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:test-limit-reached');
    }

    public function test_only_a_draft_can_be_deleted(): void
    {
        $draft = $this->newTest();
        $published = $this->newTest();
        $this->sectionWithQuestion('company', $published);
        $this->company('POST', "tests/{$published}/publish")->assertOk();

        $this->company('DELETE', "tests/{$draft}")->assertNoContent();
        $this->assertSame(0, DB::table('assessment_tests')->where('id', $draft)->count());

        $this->company('DELETE', "tests/{$published}")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:test-not-draft');
    }

    // ----- sections -----

    public function test_sections_are_added_changed_reordered_and_removed(): void
    {
        $id = $this->newTest();

        $first = (int) $this->company('POST', "tests/{$id}/sections", $this->section('One', 300, ['allow_back' => false, 'instructions' => 'Read first']))
            ->assertCreated()->assertJsonPath('data.position', 1)->assertJsonPath('data.allow_back', false)->assertJsonPath('data.allow_skip', true)->json('data.id');
        $second = (int) $this->company('POST', "tests/{$id}/sections", $this->section('Two'))->assertCreated()->assertJsonPath('data.position', 2)->json('data.id');

        $this->company('PUT', "sections/{$first}", $this->section('One, renamed', 900))->assertOk()->assertJsonPath('data.title', 'One, renamed')->assertJsonPath('data.duration_seconds', 900);

        $this->company('PUT', "tests/{$id}/sections/order", ['ids' => [$second, $first]])->assertOk()->assertJsonPath('data.0.id', $second)->assertJsonPath('data.1.position', 2);
        $this->company('PUT', "tests/{$id}/sections/order", ['ids' => [$second]])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-order');

        $this->company('GET', "tests/{$id}")->assertOk()->assertJsonPath('data.total_seconds', 1500)->assertJsonPath('data.sections.0.id', $second)->assertJsonPath('data.sections.0.question_count', 0);

        $this->company('DELETE', "sections/{$second}")->assertNoContent();
        $this->assertSame([1], DB::table('assessment_sections')->where('test_id', $id)->pluck('position')->map(fn ($p): int => (int) $p)->all());
    }

    public function test_the_section_time_and_count_follow_the_limits(): void
    {
        $id = $this->newTest();

        $this->company('POST', "tests/{$id}/sections", $this->section('Too short', 59))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-section')->assertJsonStructure(['errors' => ['duration_seconds']]);
        $this->company('POST', "tests/{$id}/sections", $this->section('Too long', 181 * 60))->assertStatus(422)->assertJsonStructure(['errors' => ['duration_seconds']]);
        $this->company('POST', "tests/{$id}/sections", ['title' => 'No time', 'format' => 'questions'])->assertStatus(422)->assertJsonStructure(['errors' => ['duration_seconds']]);

        $this->overrideSetting('assessment.max_section_minutes', 300);
        $this->company('POST', "tests/{$id}/sections", $this->section('Long now', 250 * 60))->assertCreated();

        $this->overrideSetting('assessment.max_sections_per_test', 2);
        $this->company('POST', "tests/{$id}/sections", $this->section('Second'))->assertCreated();
        $this->company('POST', "tests/{$id}/sections", $this->section('Third'))->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:section-limit-reached');
    }

    public function test_a_skill_test_cannot_use_the_psychological_formats(): void
    {
        $id = $this->newTest();

        $this->company('POST', "tests/{$id}/sections", ['title' => 'Columns', 'format' => 'speed_columns', 'settings' => ['columns' => 10, 'digits_per_column' => 40, 'seconds_per_column' => 30]])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['format']]);
        $this->company('POST', "tests/{$id}/sections", ['title' => 'Drawing', 'format' => 'drawing', 'duration_seconds' => 600])->assertStatus(422)->assertJsonStructure(['errors' => ['format']]);
        $this->company('POST', "tests/{$id}/sections", $this->section('With settings', 600, ['settings' => ['columns' => 5]]))->assertStatus(422)->assertJsonStructure(['errors' => ['settings']]);
    }

    public function test_the_format_of_a_section_is_fixed(): void
    {
        $id = $this->newTest();
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        $this->company('PUT', "sections/{$sectionId}", ['title' => 'Basics', 'format' => 'drawing', 'duration_seconds' => 600])->assertStatus(422)->assertJsonStructure(['errors' => ['format']]);
    }

    // ----- questions -----

    public function test_each_objective_type_is_stored_with_its_options(): void
    {
        $id = $this->newTest();
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        $this->company('POST', "sections/{$sectionId}/questions", $this->choice())
            ->assertCreated()->assertJsonPath('data.position', 1)->assertJsonPath('data.points', 2)->assertJsonPath('data.options.0.label', '7')->assertJsonPath('data.options.0.is_correct', true);
        $this->company('POST', "sections/{$sectionId}/questions", [
            'type' => 'multiple_response', 'text' => 'Which are even?', 'points' => 3, 'options' => [['label' => '2', 'is_correct' => true], ['label' => '3'], ['label' => '4', 'is_correct' => true]],
        ])->assertCreated();
        $this->company('POST', "sections/{$sectionId}/questions", [
            'type' => 'true_false', 'text' => 'The sky is green.', 'options' => [['label' => 'True'], ['label' => 'False', 'is_correct' => true]],
        ])->assertCreated();
        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'number', 'text' => 'How much is 6 times 7?', 'options' => [['label' => '42']]])
            ->assertCreated()->assertJsonPath('data.options.0.is_correct', true);
        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'short_text', 'text' => 'Capital of France?', 'options' => [['label' => 'Paris'], ['label' => 'paris, france']]])
            ->assertCreated()->assertJsonPath('data.options.1.is_correct', true);
        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'long_text', 'text' => 'Explain how you would plan a release.', 'points' => 10])
            ->assertCreated()->assertJsonPath('data.options', []);

        $this->company('GET', "sections/{$sectionId}/questions")->assertOk()->assertJsonCount(6, 'data')->assertJsonPath('data.5.type', 'long_text');
        $this->company('GET', "tests/{$id}")->assertJsonPath('data.sections.0.question_count', 6);
    }

    public function test_a_question_is_checked_against_its_type(): void
    {
        $id = $this->newTest();
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');
        $post = fn (array $body) => $this->company('POST', "sections/{$sectionId}/questions", $body)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-question');

        $post($this->choice('Which is a prime number?', ['options' => [['label' => '7'], ['label' => '8']]]))->assertJsonStructure(['errors' => ['options']]);
        $post($this->choice('Which is a prime number?', ['options' => [['label' => '7', 'is_correct' => true], ['label' => '8', 'is_correct' => true]]]))->assertJsonStructure(['errors' => ['options']]);
        $post($this->choice('Which is a prime number?', ['options' => [['label' => '7', 'is_correct' => true], ['label' => '7']]]))->assertJsonStructure(['errors' => ['options']]);
        $post($this->choice('Which is a prime number?', ['options' => array_map(static fn (int $i): array => ['label' => "Option {$i}", 'is_correct' => $i === 1], range(1, 11))]))->assertJsonStructure(['errors' => ['options']]);
        $post($this->choice('Which is a prime number?', ['points' => 0]))->assertJsonStructure(['errors' => ['points']]);
        $post($this->choice('Hi'))->assertJsonStructure(['errors' => ['text']]);
        $post(['type' => 'multiple_response', 'text' => 'None correct here', 'options' => [['label' => 'a'], ['label' => 'b']]])->assertJsonStructure(['errors' => ['options']]);
        $post(['type' => 'true_false', 'text' => 'Three options', 'options' => [['label' => 'a', 'is_correct' => true], ['label' => 'b'], ['label' => 'c']]])->assertJsonStructure(['errors' => ['options']]);
        $post(['type' => 'number', 'text' => 'Not a number', 'options' => [['label' => 'forty two']]])->assertJsonStructure(['errors' => ['options']]);
        $post(['type' => 'long_text', 'text' => 'Long without options', 'options' => [['label' => 'a']]])->assertJsonStructure(['errors' => ['options']]);
        $post($this->choice('Which is a prime number?', ['scale_key' => 'logic']))->assertJsonStructure(['errors' => ['scale_key']]);
        $post($this->choice('Which is a prime number?', ['options' => [['label' => '7', 'is_correct' => true, 'score_value' => 3], ['label' => '8']]]))->assertJsonStructure(['errors' => ['options']]);
    }

    public function test_a_skill_test_cannot_use_the_psychological_question_types(): void
    {
        $id = $this->newTest();
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        foreach (['likert', 'forced_choice', 'drawing'] as $type) {
            $this->company('POST', "sections/{$sectionId}/questions", ['type' => $type, 'text' => 'A question of this type'])->assertStatus(422)->assertJsonStructure(['errors' => ['type']]);
        }
    }

    public function test_a_question_is_changed_reordered_and_removed(): void
    {
        $id = $this->newTest();
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');
        $first = (int) $this->company('POST', "sections/{$sectionId}/questions", $this->choice('First question here'))->assertCreated()->json('data.id');
        $second = (int) $this->company('POST', "sections/{$sectionId}/questions", $this->choice('Second question here'))->assertCreated()->json('data.id');

        $this->company('PUT', "questions/{$first}", ['type' => 'number', 'text' => 'First, now a number', 'points' => 5, 'options' => [['label' => '12']]])
            ->assertOk()->assertJsonPath('data.type', 'number')->assertJsonPath('data.position', 1)->assertJsonCount(1, 'data.options');
        $this->assertSame(1, DB::table('assessment_question_options')->where('question_id', $first)->count());

        $this->company('PUT', "sections/{$sectionId}/questions/order", ['ids' => [$second, $first]])->assertOk()->assertJsonPath('data.0.id', $second);
        $this->company('PUT', "sections/{$sectionId}/questions/order", ['ids' => [$first, $first]])->assertStatus(422);
        $this->company('PUT', "sections/{$sectionId}/questions/order", ['ids' => [$first]])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-order');

        $this->company('DELETE', "questions/{$second}")->assertNoContent();
        $this->assertSame(0, DB::table('assessment_question_options')->where('question_id', $second)->count());
        $this->assertSame(1, (int) DB::table('assessment_questions')->where('id', $first)->value('position'));
    }

    public function test_the_questions_of_another_company_stay_hidden(): void
    {
        $id = $this->newTest();
        $sectionId = $this->sectionWithQuestion('company', $id);
        $questionId = (int) DB::table('assessment_questions')->value('id');

        $this->company('GET', "sections/{$sectionId}/questions", [], $this->otherAdminToken)->assertNotFound();
        $this->company('POST', "sections/{$sectionId}/questions", $this->choice(), $this->otherAdminToken)->assertNotFound();
        $this->company('PUT', "questions/{$questionId}", $this->choice(), $this->otherAdminToken)->assertNotFound();
        $this->company('DELETE', "questions/{$questionId}", [], $this->otherAdminToken)->assertNotFound();
        $this->company('DELETE', "sections/{$sectionId}", [], $this->otherAdminToken)->assertNotFound();
    }

    public function test_the_question_limit_of_a_section_applies(): void
    {
        $this->overrideSetting('assessment.max_questions_per_section', 2);
        $id = $this->newTest();
        $sectionId = $this->sectionWithQuestion('company', $id);
        $this->company('POST', "sections/{$sectionId}/questions", $this->choice('Second question here'))->assertCreated();

        $this->company('POST', "sections/{$sectionId}/questions", $this->choice('Third question here'))->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:question-limit-reached');
    }

    public function test_only_one_sample_question_per_type_is_allowed(): void
    {
        $id = $this->newTest();
        $sectionId = (int) $this->company('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        $first = (int) $this->company('POST', "sections/{$sectionId}/questions", $this->choice('First question here', ['is_sample' => true]))->assertCreated()->assertJsonPath('data.is_sample', true)->json('data.id');
        $this->company('POST', "sections/{$sectionId}/questions", $this->choice('Second question here', ['is_sample' => true]))->assertStatus(422)->assertJsonStructure(['errors' => ['is_sample']]);
        $this->company('POST', "sections/{$sectionId}/questions", ['type' => 'long_text', 'text' => 'A long answer question', 'is_sample' => true])->assertCreated();

        $this->company('PUT', "questions/{$first}", $this->choice('First question here', ['is_sample' => true]))->assertOk();
    }

    // ----- publishing, archiving, cloning -----

    public function test_an_incomplete_test_is_not_published_and_the_problems_are_listed(): void
    {
        $id = $this->newTest();
        $this->company('POST', "tests/{$id}/publish")->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:test-incomplete')->assertJsonCount(1, 'errors.test');

        $this->company('POST', "tests/{$id}/sections", $this->section('Empty section'))->assertCreated();
        $drawn = (int) $this->company('POST', "tests/{$id}/sections", $this->section('Drawn', 600, ['draw_count' => 3]))->assertCreated()->json('data.id');
        $this->company('POST', "sections/{$drawn}/questions", $this->choice())->assertCreated();

        $this->company('POST', "tests/{$id}/publish")->assertStatus(422)->assertJsonCount(2, 'errors.test');
        $this->assertSame('draft', DB::table('assessment_tests')->where('id', $id)->value('status'));
    }

    public function test_a_complete_test_is_published_once(): void
    {
        $id = $this->newTest();
        $this->sectionWithQuestion('company', $id);

        $this->company('POST', "tests/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.published_at', '2026-10-05T09:00:00+00:00');
        $this->company('POST', "tests/{$id}/publish")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:test-not-draft');
    }

    public function test_archiving_and_restoring_are_repeatable_and_keep_the_publication(): void
    {
        $id = $this->newTest();
        $this->sectionWithQuestion('company', $id);
        $this->company('POST', "tests/{$id}/publish")->assertOk();

        $this->company('POST', "tests/{$id}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
        $this->company('POST', "tests/{$id}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
        $this->company('POST', "tests/{$id}/restore")->assertOk()->assertJsonPath('data.status', 'published');

        $draft = $this->newTest();
        $this->company('POST', "tests/{$draft}/archive")->assertOk();
        $this->company('POST', "tests/{$draft}/restore")->assertOk()->assertJsonPath('data.status', 'draft');
    }

    public function test_a_clone_copies_the_content_into_a_new_draft(): void
    {
        $id = $this->newTest();
        $sectionId = $this->sectionWithQuestion('company', $id, $this->choice('Which is a prime number?', ['is_sample' => true]));
        $this->company('POST', "tests/{$id}/publish")->assertOk();

        $copy = (int) $this->company('POST', "tests/{$id}/clone")->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.title', 'Logic and numbers (copy)')->json('data.id');

        $this->assertNotSame($id, $copy);
        $details = $this->company('GET', "tests/{$copy}")->assertOk();
        $details->assertJsonCount(1, 'data.sections')->assertJsonPath('data.sections.0.question_count', 1)->assertJsonPath('data.total_seconds', 600);
        $this->assertNotSame($sectionId, $details->json('data.sections.0.id'));

        $questions = $this->company('GET', 'sections/'.$details->json('data.sections.0.id').'/questions')->assertOk();
        $questions->assertJsonPath('data.0.options.0.is_correct', true)->assertJsonPath('data.0.is_sample', true);
        $this->company('GET', "tests/{$id}")->assertJsonPath('data.status', 'published');
    }

    // ----- the system library -----

    public function test_an_admin_writes_the_library_and_a_company_reads_only_published_tests(): void
    {
        $draft = $this->newTest('library', ['title' => 'Excel basics']);
        $live = $this->newTest('library', ['title' => 'Attention to detail', 'description' => 'Spot the differences', 'measured_capabilities' => ['Accuracy']]);
        $this->sectionWithQuestion('library', $live, $this->choice('Which is a prime number?', ['is_sample' => true]));
        $this->library('POST', "tests/{$live}/publish")->assertOk();

        $row = DB::table('assessment_tests')->where('id', $live)->first();
        $this->assertNull($row->company_id);
        $this->assertNull($row->provider_id);
        $this->assertSame($this->assessmentAdminId, (int) $row->created_by_admin_id);

        $this->company('GET', 'library')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Attention to detail')->assertJsonPath('data.0.measured_capabilities', ['Accuracy']);
        $this->company('GET', 'library?filter[q]=excel')->assertOk()->assertJsonCount(0, 'data');
        $this->library('GET', 'tests?filter[status]=draft')->assertOk()->assertJsonPath('data.0.id', $draft);

        $this->company('GET', "tests/{$live}")->assertNotFound();
        $this->company('POST', "tests/{$live}/clone")->assertNotFound();
    }

    public function test_the_catalogue_shows_samples_without_the_answers_and_never_the_bank(): void
    {
        $id = $this->newTest('library', ['title' => 'Attention to detail']);
        $sectionId = $this->sectionWithQuestion('library', $id, $this->choice('Which is a prime number?', ['is_sample' => true]));
        $this->library('POST', "sections/{$sectionId}/questions", $this->choice('A hidden question in the bank'))->assertCreated();
        $this->library('POST', "sections/{$sectionId}/questions", ['type' => 'number', 'text' => 'A number sample here', 'options' => [['label' => '42']], 'is_sample' => true])->assertCreated();
        $this->library('POST', "tests/{$id}/publish")->assertOk();

        $response = $this->company('GET', "catalog/{$id}", [], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.title', 'Attention to detail')->assertJsonPath('data.section_count', 1)->assertJsonPath('data.total_seconds', 600)
            ->assertJsonCount(2, 'data.samples')->assertJsonPath('data.samples.0.options', ['7', '8', '9'])->assertJsonPath('data.samples.1.options', []);

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertStringNotContainsString('A hidden question', $response->getContent());
        $this->assertStringNotContainsString('42', $response->getContent());
    }

    public function test_a_draft_or_an_archived_library_test_is_not_in_the_catalogue(): void
    {
        $id = $this->newTest('library');
        $this->sectionWithQuestion('library', $id);

        $this->company('GET', "catalog/{$id}")->assertNotFound();

        $this->library('POST', "tests/{$id}/publish")->assertOk();
        $this->company('GET', "catalog/{$id}")->assertOk();

        $this->library('POST', "tests/{$id}/archive")->assertOk();
        $this->company('GET', "catalog/{$id}")->assertNotFound();
        $this->company('GET', 'library')->assertJsonCount(0, 'data');
    }

    public function test_the_catalogue_is_for_company_members_only(): void
    {
        $seeker = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en']);
        $token = $this->tokenFor((int) $seeker->id);

        $this->company('GET', 'library', [], $token)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
    }
}
