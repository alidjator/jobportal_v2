<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Recruitment\Application\ScreeningQuestionUsage;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class ScreeningQuestionTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /** @param  list<string>  $labels */
    private function create(string $type = 'single_choice', string $text = 'Where can you work?', array $labels = ['Jakarta', 'Bandung'], ?string $token = null): int
    {
        $body = ['type' => $type, 'text' => $text];

        if ($labels !== []) {
            $body['options'] = array_map(fn (string $label): array => ['label' => $label], $labels);
        }

        return (int) $this->api('POST', '/company/screening-questions', $body, $token)->assertCreated()->json('data.id');
    }

    /** @return list<string> */
    private function labels(int $id): array
    {
        return DB::table('recruitment_screening_question_options')->where('question_id', $id)->orderBy('position')->pluck('label')->all();
    }

    private function optionId(int $questionId, string $label): int
    {
        return (int) DB::table('recruitment_screening_question_options')->where('question_id', $questionId)->where('label', $label)->value('id');
    }

    private function attach(int $questionId, ?int $jobId = null): int
    {
        return (int) DB::table('recruitment_job_questions')->insertGetId([
            'job_id' => $jobId ?? $this->insertJob(), 'question_id' => $questionId, 'position' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_library_needs_a_signed_in_company_member(): void
    {
        $this->getJson('/api/v1/company/screening-questions')->assertStatus(401);

        $stranger = $this->jobMember($this->companyId, 'X', 'x@example.test', 'recruiter');
        DB::table('company_members')->where('user_id', $stranger)->delete();
        $this->api('GET', '/company/screening-questions', token: $this->tokenFor($stranger))->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
    }

    public function test_a_suspended_company_cannot_use_its_library(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->api('GET', '/company/screening-questions')->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-suspended');
    }

    public function test_recruiters_read_but_only_company_admins_change(): void
    {
        $id = $this->create('yes_no', 'Can you start next month?', []);

        $this->api('GET', '/company/screening-questions', token: $this->recruiterToken)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->api('GET', "/company/screening-questions/{$id}", token: $this->recruiterToken)->assertOk();

        foreach ([
            ['POST', '/company/screening-questions', ['type' => 'yes_no', 'text' => 'Do you drive a car?']],
            ['PATCH', "/company/screening-questions/{$id}", ['text' => 'Another text here']],
            ['DELETE', "/company/screening-questions/{$id}", []],
            ['POST', "/company/screening-questions/{$id}/archive", []],
            ['POST', "/company/screening-questions/{$id}/restore", []],
        ] as [$method, $uri, $body]) {
            $this->api($method, $uri, $body, $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        }

        $this->assertSame('Can you start next month?', DB::table('recruitment_screening_questions')->where('id', $id)->value('text'));
    }

    public function test_every_type_can_be_created(): void
    {
        $choice = $this->api('POST', '/company/screening-questions', [
            'type' => 'multiple_choice', 'text' => '  Which tools do you use?  ', 'options' => [['label' => ' Git '], ['label' => 'Docker'], ['label' => 'Make']],
        ])->assertCreated();

        $choice->assertJsonPath('data.type', 'multiple_choice')->assertJsonPath('data.text', 'Which tools do you use?')->assertJsonPath('data.archived_at', null)
            ->assertJsonPath('data.options.0.label', 'Git')->assertJsonPath('data.options.2.label', 'Make');
        $this->assertCount(3, $choice->json('data.options'));

        foreach (['text' => 'Why do you want this job?', 'yes_no' => 'Can you work on weekends?', 'number' => 'How many years of experience do you have?'] as $type => $text) {
            $this->api('POST', '/company/screening-questions', ['type' => $type, 'text' => $text])->assertCreated()->assertJsonPath('data.options', []);
        }

        $this->assertSame(4, DB::table('recruitment_screening_questions')->where('company_id', $this->companyId)->count());
        $this->assertSame($this->adminId, (int) DB::table('recruitment_screening_questions')->value('created_by_user_id'));
    }

    public function test_a_bad_question_is_refused_with_the_field_that_is_wrong(): void
    {
        $this->api('POST', '/company/screening-questions', ['text' => 'Without a type'])->assertStatus(422);
        $this->api('POST', '/company/screening-questions', ['type' => 'essay', 'text' => 'Unknown type'])->assertStatus(422);
        $this->api('POST', '/company/screening-questions', ['type' => 'yes_no', 'text' => 'abcd'])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-screening-question')->assertJsonStructure(['errors' => ['text']]);
        $this->api('POST', '/company/screening-questions', ['type' => 'single_choice', 'text' => 'Only one option'])->assertStatus(422)->assertJsonStructure(['errors' => ['options']]);
        $this->api('POST', '/company/screening-questions', ['type' => 'single_choice', 'text' => 'Same options', 'options' => [['label' => 'A'], ['label' => 'a']]])->assertStatus(422);
        $this->api('POST', '/company/screening-questions', ['type' => 'yes_no', 'text' => 'Yes or no with options', 'options' => [['label' => 'A'], ['label' => 'B']]])->assertStatus(422)->assertJsonStructure(['errors' => ['options']]);

        $this->assertSame(0, DB::table('recruitment_screening_questions')->count());
    }

    public function test_the_list_shows_the_active_questions_newest_first_and_can_search_and_filter(): void
    {
        $first = $this->create('yes_no', 'Can you relocate to Bandung?', []);
        $second = $this->create('number', 'Years of PHP experience?', []);
        $archived = $this->create('text', 'An old archived question', []);
        $this->api('POST', "/company/screening-questions/{$archived}/archive")->assertOk();
        $this->create('yes_no', 'Question of the other company', [], $this->otherAdminToken);

        $this->assertSame([$second, $first], array_column($this->api('GET', '/company/screening-questions')->assertOk()->json('data'), 'id'));
        $this->assertSame([$archived], array_column($this->api('GET', '/company/screening-questions?filter[archived]=true')->json('data'), 'id'));
        $this->assertSame([$first], array_column($this->api('GET', '/company/screening-questions?filter[q]=BANDUNG')->json('data'), 'id'));
        $this->assertSame([], $this->api('GET', '/company/screening-questions?filter[q]=%25')->json('data'));
        $this->api('GET', '/company/screening-questions?filter[color]=red')->assertStatus(422);

        $page = $this->api('GET', '/company/screening-questions?per_page=1&page=2')->assertOk();
        $this->assertSame([$first], array_column($page->json('data'), 'id'));
        $this->assertSame(2, $page->json('meta.total'));
    }

    public function test_a_question_of_another_company_does_not_exist(): void
    {
        $foreign = $this->create('yes_no', 'Question of the other company', [], $this->otherAdminToken);

        $this->api('GET', "/company/screening-questions/{$foreign}")->assertStatus(404);
        $this->api('PATCH', "/company/screening-questions/{$foreign}", ['text' => 'Taken over by someone'])->assertStatus(404);
        $this->api('DELETE', "/company/screening-questions/{$foreign}")->assertStatus(404);
        $this->api('POST', "/company/screening-questions/{$foreign}/archive")->assertStatus(404);
        $this->assertNull(DB::table('recruitment_screening_questions')->where('id', $foreign)->value('archived_at'));
    }

    public function test_the_text_can_change_alone(): void
    {
        $id = $this->create();

        $this->api('PATCH', "/company/screening-questions/{$id}", ['text' => 'Where would you like to work?'])->assertOk()
            ->assertJsonPath('data.text', 'Where would you like to work?')->assertJsonPath('data.options.0.label', 'Jakarta');
        $this->api('PATCH', "/company/screening-questions/{$id}", [])->assertStatus(422);
        $this->api('PATCH', "/company/screening-questions/{$id}", ['text' => 'abc'])->assertStatus(422)->assertJsonStructure(['errors' => ['text']]);
    }

    public function test_choices_are_renamed_added_removed_and_reordered_by_the_list(): void
    {
        $id = $this->create('single_choice', 'Where can you work?', ['Jakarta', 'Bandung', 'Surabaya']);
        $jakarta = $this->optionId($id, 'Jakarta');
        $bandung = $this->optionId($id, 'Bandung');

        $response = $this->api('PATCH', "/company/screening-questions/{$id}", ['options' => [
            ['id' => $bandung, 'label' => 'Bandung City'], ['label' => 'Medan'], ['id' => $jakarta, 'label' => 'Jakarta'],
        ]])->assertOk();

        $this->assertSame(['Bandung City', 'Medan', 'Jakarta'], array_column($response->json('data.options'), 'label'));
        $this->assertSame([$bandung, $jakarta], [$response->json('data.options.0.id'), $response->json('data.options.2.id')]);
        $this->assertSame(['Bandung City', 'Medan', 'Jakarta'], $this->labels($id));
        $this->assertSame(0, DB::table('recruitment_screening_question_options')->where('label', 'Surabaya')->count());
    }

    public function test_two_choices_can_swap_their_labels(): void
    {
        $id = $this->create('single_choice', 'Pick one of them', ['Alpha', 'Beta']);
        $alpha = $this->optionId($id, 'Alpha');
        $beta = $this->optionId($id, 'Beta');

        $this->api('PATCH', "/company/screening-questions/{$id}", ['options' => [['id' => $alpha, 'label' => 'Beta'], ['id' => $beta, 'label' => 'Alpha']]])->assertOk();

        $this->assertSame('Beta', DB::table('recruitment_screening_question_options')->where('id', $alpha)->value('label'));
        $this->assertSame('Alpha', DB::table('recruitment_screening_question_options')->where('id', $beta)->value('label'));
    }

    public function test_the_new_choices_are_checked_like_new_ones(): void
    {
        $id = $this->create();
        $other = $this->create('single_choice', 'Another choice question', ['One', 'Two']);
        $foreign = $this->optionId($other, 'One');
        $jakarta = $this->optionId($id, 'Jakarta');

        $this->api('PATCH', "/company/screening-questions/{$id}", ['options' => [['label' => 'Only one']]])->assertStatus(422);
        $this->api('PATCH', "/company/screening-questions/{$id}", ['options' => [['id' => $foreign, 'label' => 'A'], ['label' => 'B']]])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-screening-question');
        $this->api('PATCH', "/company/screening-questions/{$id}", ['options' => [['id' => $jakarta, 'label' => 'A'], ['id' => $jakarta, 'label' => 'B']]])->assertStatus(422);
        $this->api('PATCH', "/company/screening-questions/{$id}", ['options' => [['label' => 'Same'], ['label' => 'SAME']]])->assertStatus(422);
        $this->assertSame(['Jakarta', 'Bandung'], $this->labels($id));
    }

    public function test_options_cannot_be_given_to_a_question_without_choices(): void
    {
        $id = $this->create('yes_no', 'Can you work on weekends?', []);

        $this->api('PATCH', "/company/screening-questions/{$id}", ['options' => [['label' => 'A'], ['label' => 'B']]])->assertStatus(422);
        $this->api('PATCH', "/company/screening-questions/{$id}", ['text' => 'Can you work at weekends?', 'options' => []])->assertOk();
    }

    public function test_a_choice_that_a_job_uses_as_its_ideal_answer_cannot_be_deleted(): void
    {
        $id = $this->create('single_choice', 'Where can you work?', ['Jakarta', 'Bandung', 'Surabaya']);
        $jakarta = $this->optionId($id, 'Jakarta');
        $bandung = $this->optionId($id, 'Bandung');
        $attached = $this->attach($id);
        DB::table('recruitment_job_question_ideal_options')->insert(['job_question_id' => $attached, 'option_id' => $jakarta]);

        $this->api('PATCH', "/company/screening-questions/{$id}", ['options' => [['id' => $bandung, 'label' => 'Bandung'], ['label' => 'Medan']]])
            ->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:question-in-use');
        $this->assertSame(['Jakarta', 'Bandung', 'Surabaya'], $this->labels($id));

        // The ideal choice may be renamed, and the others removed.
        $this->api('PATCH', "/company/screening-questions/{$id}", ['options' => [['id' => $jakarta, 'label' => 'Greater Jakarta'], ['id' => $bandung, 'label' => 'Bandung']]])->assertOk();
        $this->assertSame(['Greater Jakarta', 'Bandung'], $this->labels($id));
        $this->assertSame(1, DB::table('recruitment_job_question_ideal_options')->count());
    }

    public function test_only_a_question_no_job_uses_can_be_deleted(): void
    {
        $free = $this->create('single_choice', 'A free question', ['A', 'B']);
        $used = $this->create('yes_no', 'A used question', []);
        $this->attach($used);

        $this->api('DELETE', "/company/screening-questions/{$free}")->assertNoContent();
        $this->assertSame(0, DB::table('recruitment_screening_question_options')->count());

        $this->api('DELETE', "/company/screening-questions/{$used}")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:question-in-use');
        $this->assertSame(1, DB::table('recruitment_screening_questions')->count());
    }

    public function test_a_question_that_applications_depend_on_can_only_be_archived(): void
    {
        $id = $this->create();
        $this->app->instance(ScreeningQuestionUsage::class, new class implements ScreeningQuestionUsage
        {
            public function isLocked(int $questionId): bool
            {
                return true;
            }
        });

        $this->api('PATCH', "/company/screening-questions/{$id}", ['text' => 'A text that must not apply'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:question-in-use');
        $this->api('DELETE', "/company/screening-questions/{$id}")->assertStatus(409);
        $this->api('POST', "/company/screening-questions/{$id}/archive")->assertOk();
    }

    public function test_archiving_and_restoring_work_twice_without_harm(): void
    {
        $id = $this->create();
        $this->attach($id);

        $this->api('POST', "/company/screening-questions/{$id}/archive")->assertOk();
        $stamp = DB::table('recruitment_screening_questions')->where('id', $id)->value('archived_at');
        $this->assertNotNull($stamp);

        $this->api('POST', "/company/screening-questions/{$id}/archive")->assertOk();
        $this->assertSame($stamp, DB::table('recruitment_screening_questions')->where('id', $id)->value('archived_at'));
        $this->api('GET', "/company/screening-questions/{$id}")->assertOk()->assertJsonPath('data.archived_at', fn ($value) => $value !== null);

        $this->api('POST', "/company/screening-questions/{$id}/restore")->assertOk()->assertJsonPath('data.archived_at', null);
        $this->api('POST', "/company/screening-questions/{$id}/restore")->assertOk();
        $this->assertSame(1, DB::table('recruitment_job_questions')->count());
    }

    public function test_while_screening_is_off_nothing_can_change_but_everything_can_be_read(): void
    {
        $id = $this->create();
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::SCREENING_ENABLED, $this->companyId, false, null);

        foreach ([
            ['POST', '/company/screening-questions', ['type' => 'yes_no', 'text' => 'Can you start next month?']],
            ['PATCH', "/company/screening-questions/{$id}", ['text' => 'Another text here']],
            ['DELETE', "/company/screening-questions/{$id}", []],
            ['POST', "/company/screening-questions/{$id}/archive", []],
            ['POST', "/company/screening-questions/{$id}/restore", []],
        ] as [$method, $uri, $body]) {
            $this->api($method, $uri, $body)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:screening-disabled');
        }

        $this->api('GET', '/company/screening-questions')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->api('GET', "/company/screening-questions/{$id}", token: $this->recruiterToken)->assertOk();

        // The other company is not affected.
        $this->create('yes_no', 'Question of the other company', [], $this->otherAdminToken);
    }
}
