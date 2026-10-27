<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\JobApplicationCheck;
use Src\Recruitment\Application\RecruitmentSettings;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class ApplyScreeningPresetTest extends FeatureTestCase
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

    private function question(string $type = 'yes_no', string $text = 'Can you start next month?', array $labels = [], ?string $token = null): int
    {
        $body = ['type' => $type, 'text' => $text];

        if ($labels !== []) {
            $body['options'] = array_map(fn (string $label): array => ['label' => $label], $labels);
        }

        return (int) $this->api('POST', '/company/screening-questions', $body, $token)->assertCreated()->json('data.id');
    }

    /** @param  list<array<string, mixed>>  $questions */
    private function preset(string $name, array $questions, ?int $levelId = null, ?string $token = null): int
    {
        $body = ['name' => $name, 'questions' => $questions] + ($levelId === null ? [] : ['job_level_id' => $levelId]);

        return (int) $this->api('POST', '/company/screening-presets', $body, $token)->assertCreated()->json('data.id');
    }

    private function apply(int $jobId, int $presetId, ?string $token = null): TestResponse
    {
        return $this->api('POST', "/company/jobs/{$jobId}/screening-questions/apply-preset", ['preset_id' => $presetId], $token);
    }

    public function test_applying_copies_the_preset_and_replaces_the_list_of_the_job(): void
    {
        $old = $this->question('yes_no', 'A question the job had before');
        $yesNo = $this->question();
        $choice = $this->question('multiple_choice', 'Which languages do you write?', ['PHP', 'Go']);
        $php = (int) DB::table('recruitment_screening_question_options')->where('question_id', $choice)->where('label', 'PHP')->value('id');
        $number = $this->question('number', 'How many years have you worked?');
        $job = $this->insertJob();
        $this->api('PUT', "/company/jobs/{$job}/screening-questions", ['questions' => [['question_id' => $old]]])->assertOk();

        $id = $this->preset('Backend', [
            ['question_id' => $choice, 'must_have' => true, 'ideal' => ['option_ids' => [$php]]],
            ['question_id' => $number, 'must_have' => true, 'ideal' => ['min' => 2, 'max' => null]],
            ['question_id' => $yesNo, 'required' => true],
        ]);

        $this->apply($job, $id)->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.question_id', $choice)
            ->assertJsonPath('data.0.ideal.option_ids', [$php])
            ->assertJsonPath('data.1.ideal.min', 2)
            ->assertJsonPath('data.2.required', true);

        $this->api('GET', "/company/jobs/{$job}/screening-questions")->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.question_id', $choice);
    }

    public function test_changing_or_deleting_the_preset_afterwards_never_touches_the_job(): void
    {
        $question = $this->question();
        $job = $this->insertJob();
        $id = $this->preset('Temporary', [['question_id' => $question]]);
        $this->apply($job, $id)->assertOk();

        $this->api('PUT', "/company/screening-presets/{$id}", ['name' => 'Temporary', 'questions' => []])->assertOk();
        $this->api('GET', "/company/jobs/{$job}/screening-questions")->assertJsonCount(1, 'data');

        $this->api('DELETE', "/company/screening-presets/{$id}")->assertNoContent();
        $this->api('GET', "/company/jobs/{$job}/screening-questions")->assertJsonCount(1, 'data');
    }

    public function test_a_preset_with_an_archived_question_is_refused_and_names_it(): void
    {
        $active = $this->question();
        $archived = $this->question('yes_no', 'Question that gets archived');
        $job = $this->insertJob();
        $id = $this->preset('Mixed', [['question_id' => $active], ['question_id' => $archived]]);
        $this->api('POST', "/company/screening-questions/{$archived}/archive")->assertOk();

        $this->apply($job, $id)->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-job-questions')
            ->assertJsonStructure(['errors' => ['questions.1.question_id']]);
        $this->api('GET', "/company/jobs/{$job}/screening-questions")->assertJsonCount(0, 'data');
    }

    public function test_the_limit_the_locks_and_the_switch_of_a_job_list_apply(): void
    {
        $ids = [$this->question('yes_no', 'Question number one'), $this->question('yes_no', 'Question number two')];
        $id = $this->preset('Two', array_map(static fn (int $question): array => ['question_id' => $question], $ids));
        $settings = $this->app->make(SettingsStore::class);

        $settings->setCompanyOverride(RecruitmentSettings::MAX_SCREENING_QUESTIONS, $this->companyId, 1, null);
        $this->apply($this->insertJob('Limited'), $id)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:screening-limit-reached');
        $settings->setCompanyOverride(RecruitmentSettings::MAX_SCREENING_QUESTIONS, $this->companyId, 20, null);

        foreach (['pending_review', 'taken_down'] as $status) {
            $this->apply($this->insertJob("In {$status}", $status), $id)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-not-editable');
        }

        $job = $this->insertJob('Locked');
        $this->app->instance(JobApplicationCheck::class, new class implements JobApplicationCheck
        {
            public function hasApplications(int $jobId): bool
            {
                return true;
            }
        });
        $this->apply($job, $id)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-questions-locked');

        $settings->setCompanyOverride(RecruitmentSettings::SCREENING_ENABLED, $this->companyId, false, null);
        $this->apply($this->insertJob('Switched off'), $id)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:screening-disabled');
    }

    public function test_a_preset_of_another_company_a_job_nobody_may_see_and_an_unknown_id_do_not_exist(): void
    {
        $job = $this->insertJob();
        $foreign = $this->preset('Foreign', [], token: $this->otherAdminToken);

        $this->apply($job, $foreign)->assertStatus(404);
        $this->apply($job, 999999)->assertStatus(404);
        $this->apply($this->insertJob('Foreign job', 'draft', [$this->otherAdminId], $this->otherCompanyId), $this->preset('Mine', []))->assertStatus(404);
        $this->api('POST', "/company/jobs/{$job}/screening-questions/apply-preset", [])->assertStatus(422);
    }

    public function test_a_member_who_cannot_see_the_job_cannot_apply(): void
    {
        $job = $this->insertJob('Not assigned', 'draft', [$this->adminId]);
        $id = $this->preset('Any', []);

        $this->apply($job, $id, $this->recruiterToken)->assertStatus(404);
    }

    public function test_the_menu_of_a_job_puts_the_presets_of_its_level_first_and_marks_them(): void
    {
        $junior = $this->referenceId('reference_job_levels', 'junior');
        $senior = $this->referenceId('reference_job_levels', 'senior');
        $this->preset('Alpha no level', []);
        $this->preset('Beta senior', [], $senior);
        $this->preset('Zeta junior', [], $junior);
        $this->preset('Gamma junior', [], $junior);
        $withLevel = $this->insertJob('Junior job', 'draft', attributes: ['job_level_id' => $junior]);
        $withoutLevel = $this->insertJob('Plain job');

        $response = $this->api('GET', "/company/jobs/{$withLevel}/screening-presets")->assertOk();
        $this->assertSame(['Gamma junior', 'Zeta junior', 'Alpha no level', 'Beta senior'], $response->json('data.*.name'));
        $this->assertSame([true, true, false, false], $response->json('data.*.suggested'));

        $this->assertSame([false, false, false, false], $this->api('GET', "/company/jobs/{$withoutLevel}/screening-presets")->assertOk()->json('data.*.suggested'));
        $this->api('GET', "/company/jobs/{$withLevel}/screening-presets?filter[q]=x")->assertStatus(422);
    }
}
