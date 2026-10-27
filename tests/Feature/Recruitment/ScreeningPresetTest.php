<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class ScreeningPresetTest extends FeatureTestCase
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
    private function question(string $type = 'yes_no', string $text = 'Can you start next month?', array $labels = [], ?string $token = null): int
    {
        $body = ['type' => $type, 'text' => $text];

        if ($labels !== []) {
            $body['options'] = array_map(fn (string $label): array => ['label' => $label], $labels);
        }

        return (int) $this->api('POST', '/company/screening-questions', $body, $token)->assertCreated()->json('data.id');
    }

    private function optionId(int $questionId, string $label): int
    {
        return (int) DB::table('recruitment_screening_question_options')->where('question_id', $questionId)->where('label', $label)->value('id');
    }

    /** @param  list<array<string, mixed>>  $questions */
    private function save(string $name, array $questions = [], ?int $levelId = null, ?int $id = null, ?string $token = null): TestResponse
    {
        $body = ['name' => $name, 'questions' => $questions];

        if ($levelId !== null) {
            $body['job_level_id'] = $levelId;
        }

        return $id === null ? $this->api('POST', '/company/screening-presets', $body, $token) : $this->api('PUT', "/company/screening-presets/{$id}", $body, $token);
    }

    private function preset(string $name = 'Junior developer', ?int $levelId = null): int
    {
        return (int) $this->save($name, [['question_id' => $this->question()]], $levelId)->assertCreated()->json('data.id');
    }

    public function test_a_preset_is_created_and_read_with_its_questions_in_order(): void
    {
        $yesNo = $this->question();
        $choice = $this->question('single_choice', 'Where can you work?', ['Jakarta', 'Bandung']);
        $jakarta = $this->optionId($choice, 'Jakarta');
        $level = $this->referenceId('reference_job_levels', 'junior');

        $id = $this->save('  Junior developer ', [
            ['question_id' => $choice, 'must_have' => true, 'ideal' => ['option_ids' => [$jakarta]]],
            ['question_id' => $yesNo],
        ], $level)->assertCreated()
            ->assertJsonPath('data.name', 'Junior developer')
            ->assertJsonPath('data.job_level_id', $level)
            ->assertJsonPath('data.question_count', 2)
            ->assertJsonPath('data.questions.0.question_id', $choice)
            ->assertJsonPath('data.questions.0.required', true)
            ->assertJsonPath('data.questions.0.ideal.option_ids', [$jakarta])
            ->assertJsonPath('data.questions.1.question_id', $yesNo)
            ->json('data.id');

        $this->api('GET', "/company/screening-presets/{$id}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.questions.1.required', false);
    }

    public function test_recruiters_read_but_only_company_admins_change(): void
    {
        $id = $this->preset();

        $this->api('GET', '/company/screening-presets', token: $this->recruiterToken)->assertOk()->assertJsonPath('data.0.id', $id);

        foreach ([['POST', '/company/screening-presets'], ['PUT', "/company/screening-presets/{$id}"], ['DELETE', "/company/screening-presets/{$id}"]] as [$method, $uri]) {
            $this->api($method, $uri, ['name' => 'Another', 'questions' => []], $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        }

        $this->assertSame(1, DB::table('recruitment_screening_presets')->count());
    }

    public function test_a_preset_of_another_company_does_not_exist(): void
    {
        $foreign = (int) $this->save('Foreign', [], token: $this->otherAdminToken)->assertCreated()->json('data.id');

        $this->api('GET', "/company/screening-presets/{$foreign}")->assertStatus(404);
        $this->save('Taken over', [], id: $foreign)->assertStatus(404);
        $this->api('DELETE', "/company/screening-presets/{$foreign}")->assertStatus(404);
        $this->api('GET', '/company/screening-presets')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame('Foreign', DB::table('recruitment_screening_presets')->where('id', $foreign)->value('name'));
    }

    public function test_the_name_is_checked_and_unique_per_company_ignoring_letter_case(): void
    {
        $id = $this->preset('Senior');

        $this->save('senior')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-screening-preset')->assertJsonStructure(['errors' => ['name']]);
        $this->save(str_repeat('a', 101))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-screening-preset');
        $this->save('SENIOR', id: $id)->assertOk();
        $this->save('Senior', [], token: $this->otherAdminToken)->assertCreated();
    }

    public function test_the_level_hint_must_be_an_active_job_level(): void
    {
        $inactive = $this->referenceId('reference_job_levels', 'retired', ['is_active' => false]);

        $this->save('Retired level', [], $inactive)->assertStatus(422)->assertJsonStructure(['errors' => ['job_level_id']]);
        $this->save('Unknown level', [], 999999)->assertStatus(422)->assertJsonStructure(['errors' => ['job_level_id']]);
        $this->save('No level')->assertCreated()->assertJsonPath('data.job_level_id', null);
    }

    public function test_the_questions_follow_the_rules_of_a_jobs_list(): void
    {
        $text = $this->question('text', 'Tell us about yourself');
        $yesNo = $this->question();
        $foreign = $this->question('yes_no', 'Question of the other company', [], $this->otherAdminToken);
        $archived = $this->question('yes_no', 'Archived question here');
        $this->api('POST', "/company/screening-questions/{$archived}/archive")->assertOk();

        $this->save('Bad', [
            ['question_id' => $text, 'must_have' => true],
            ['question_id' => $yesNo, 'must_have' => true],
            ['question_id' => $foreign],
            ['question_id' => $archived],
        ])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-screening-preset')
            ->assertJsonStructure(['errors' => ['questions.0.must_have', 'questions.1.ideal', 'questions.2.question_id', 'questions.3.question_id']]);

        $this->assertSame(0, DB::table('recruitment_screening_presets')->count());
    }

    public function test_an_update_replaces_everything_and_keeps_an_archived_question_it_already_holds(): void
    {
        $first = $this->question();
        $second = $this->question('yes_no', 'Do you drive a car?');
        $id = (int) $this->save('Driver', [['question_id' => $first], ['question_id' => $second]])->assertCreated()->json('data.id');
        $this->api('POST', "/company/screening-questions/{$first}/archive")->assertOk();

        $this->save('Driver 2', [['question_id' => $second, 'required' => true], ['question_id' => $first]], id: $id)->assertOk()
            ->assertJsonPath('data.name', 'Driver 2')
            ->assertJsonPath('data.questions.0.question_id', $second)
            ->assertJsonPath('data.questions.1.archived', true);
        $this->assertSame(1, DB::table('recruitment_screening_presets')->count());
    }

    public function test_a_preset_is_limited_like_a_job_but_a_list_that_adds_nothing_is_always_accepted(): void
    {
        $ids = [$this->question('yes_no', 'Question number one'), $this->question('yes_no', 'Question number two'), $this->question('yes_no', 'Question number three')];
        $items = array_map(static fn (int $id): array => ['question_id' => $id], $ids);
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::MAX_SCREENING_QUESTIONS, $this->companyId, 2, null);

        $this->save('Too long', $items)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:screening-limit-reached');

        $id = (int) $this->save('Two', array_slice($items, 0, 2))->assertCreated()->json('data.id');
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::MAX_SCREENING_QUESTIONS, $this->companyId, 1, null);
        $this->save('Two renamed', array_slice($items, 0, 2), id: $id)->assertOk();
        $this->save('Two renamed', $items, id: $id)->assertStatus(409);
    }

    public function test_the_list_is_by_name_and_can_search_and_filter_by_level(): void
    {
        $junior = $this->referenceId('reference_job_levels', 'junior');
        $this->save('Zeta', [], $junior)->assertCreated();
        $this->save('alpha developer')->assertCreated();
        $this->save('Beta developer', [], $junior)->assertCreated();

        $this->api('GET', '/company/screening-presets')->assertOk()->assertJsonPath('data.0.name', 'alpha developer')->assertJsonPath('data.2.name', 'Zeta');
        $this->api('GET', '/company/screening-presets?filter[q]=DEVELOPER')->assertOk()->assertJsonCount(2, 'data');
        $this->api('GET', "/company/screening-presets?filter[job_level_id]={$junior}")->assertOk()->assertJsonCount(2, 'data');
        $this->api('GET', '/company/screening-presets?filter[other]=1')->assertStatus(422);
    }

    public function test_a_question_or_choice_a_preset_uses_cannot_be_deleted_but_can_be_archived(): void
    {
        $choice = $this->question('single_choice', 'Where can you work?', ['Jakarta', 'Bandung']);
        $jakarta = $this->optionId($choice, 'Jakarta');
        $this->save('Local', [['question_id' => $choice, 'must_have' => true, 'ideal' => ['option_ids' => [$jakarta]]]])->assertCreated();

        $this->api('DELETE', "/company/screening-questions/{$choice}")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:question-in-use');
        $this->api('PATCH', "/company/screening-questions/{$choice}", ['options' => [['id' => $this->optionId($choice, 'Bandung'), 'label' => 'Bandung'], ['label' => 'Depok']]])
            ->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:question-in-use');
        $this->api('POST', "/company/screening-questions/{$choice}/archive")->assertOk();
    }

    public function test_deleting_a_preset_frees_its_questions(): void
    {
        $question = $this->question();
        $id = (int) $this->save('Temporary', [['question_id' => $question]])->assertCreated()->json('data.id');

        $this->api('DELETE', "/company/screening-presets/{$id}")->assertNoContent();
        $this->api('GET', "/company/screening-presets/{$id}")->assertStatus(404);
        $this->api('DELETE', "/company/screening-questions/{$question}")->assertNoContent();
    }

    public function test_while_screening_is_off_nothing_can_change_but_everything_can_be_read(): void
    {
        $id = $this->preset();
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::SCREENING_ENABLED, $this->companyId, false, null);

        foreach ([['POST', '/company/screening-presets'], ['PUT', "/company/screening-presets/{$id}"], ['DELETE', "/company/screening-presets/{$id}"]] as [$method, $uri]) {
            $this->api($method, $uri, ['name' => 'Another', 'questions' => []])->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:screening-disabled');
        }

        $this->api('GET', '/company/screening-presets')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->api('GET', "/company/screening-presets/{$id}", token: $this->recruiterToken)->assertOk();
    }
}
