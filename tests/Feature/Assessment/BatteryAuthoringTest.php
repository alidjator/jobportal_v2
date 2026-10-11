<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/** Phase 6, part 3: a psychologist writes the batteries of the provider with norms and a report template; an admin grants them to companies. */
final class BatteryAuthoringTest extends FeatureTestCase
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

    /** @return array<string, mixed> a Likert statement of one scale */
    private function likert(string $text = 'I enjoy meeting new people.', string $scale = 'extraversion', array $extra = []): array
    {
        return [
            'type' => 'likert', 'text' => $text, 'scale_key' => $scale,
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Sometimes', 'score_value' => 2], ['label' => 'Always', 'score_value' => 3]],
            ...$extra,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function norms(string $scale = 'extraversion'): array
    {
        return [
            ['scale_key' => $scale, 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 85.5, 'band' => 'Low'],
            ['scale_key' => $scale, 'raw_min' => 11, 'raw_max' => 20, 'standard_score' => 100, 'band' => 'Average'],
        ];
    }

    /** A battery with one Likert question that has norms and a report template, ready to publish. */
    private function readyBattery(string $title = 'Personality profile'): int
    {
        $id = $this->newTest('provider', ['title' => $title]);
        $this->sectionWithQuestion('provider', $id, $this->likert());
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => $this->norms()])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Dear {{candidate_name}}, your extraversion is {{scale:extraversion:band}}.'])->assertOk();

        return $id;
    }

    // ----- access -----

    public function test_a_psychologist_writes_batteries_for_the_provider(): void
    {
        $id = (int) $this->provider('POST', 'tests', ['title' => 'Personality profile', 'measured_capabilities' => ['Extraversion']])
            ->assertCreated()->assertJsonPath('data.kind', 'psychological')->assertJsonPath('data.status', 'draft')->json('data.id');

        $row = DB::table('assessment_tests')->where('id', $id)->first();
        $this->assertSame($this->providerId, (int) $row->provider_id);
        $this->assertNull($row->company_id);
        $this->assertNotNull($row->created_by_user_id);

        $this->provider('GET', 'tests')->assertOk()->assertJsonPath('meta.total', 1);
        $this->provider('GET', 'tests', [], $this->otherPsychologistToken)->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_another_provider_never_sees_a_battery(): void
    {
        $id = $this->readyBattery();
        $other = $this->otherPsychologistToken;

        $this->provider('GET', "tests/{$id}", [], $other)->assertNotFound();
        $this->provider('PUT', "tests/{$id}", ['title' => 'Stolen title'], $other)->assertNotFound();
        $this->provider('GET', "tests/{$id}/norms", [], $other)->assertNotFound();
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => []], $other)->assertNotFound();
        $this->provider('GET', "tests/{$id}/report-template", [], $other)->assertNotFound();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Stolen'], $other)->assertNotFound();
        $this->provider('POST', "tests/{$id}/clone", [], $other)->assertNotFound();
    }

    public function test_a_suspended_psychologist_or_provider_cannot_write(): void
    {
        DB::table('assessment_psychologists')->where('provider_id', $this->providerId)->update(['status' => 'suspended']);
        $this->provider('GET', 'tests')->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:psychologist-suspended');

        DB::table('assessment_psychologists')->where('provider_id', $this->providerId)->update(['status' => 'active']);
        DB::table('assessment_providers')->where('id', $this->providerId)->update(['status' => 'suspended']);
        $this->provider('GET', 'tests')->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:provider-suspended');
    }

    public function test_the_provider_endpoints_need_a_signed_in_psychologist(): void
    {
        foreach ([['GET', '/provider/tests/1/norms'], ['PUT', '/provider/tests/1/norms'], ['GET', '/provider/tests/1/report-template'], ['PUT', '/provider/tests/1/report-template']] as [$method, $uri]) {
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
            $this->json($method, '/api/v1'.$uri)->assertStatus(401);
        }
    }

    // ----- questions of a battery -----

    public function test_a_likert_question_belongs_to_a_scale_and_scores_each_option(): void
    {
        $id = $this->newTest('provider');
        $sectionId = (int) $this->provider('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        $this->provider('POST', "sections/{$sectionId}/questions", $this->likert())
            ->assertCreated()->assertJsonPath('data.scale_key', 'extraversion')->assertJsonPath('data.points', 0)->assertJsonPath('data.options.2.score_value', 3)->assertJsonPath('data.options.0.is_correct', false);

        $post = fn (array $body) => $this->provider('POST', "sections/{$sectionId}/questions", $body)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-question');
        $post($this->likert('I enjoy meeting new people.', 'extraversion', ['scale_key' => null]))->assertJsonStructure(['errors' => ['scale_key']]);
        $post($this->likert('I enjoy meeting new people.', 'Extra Version'))->assertJsonStructure(['errors' => ['scale_key']]);
        $post($this->likert('I enjoy meeting new people.', 'extraversion', ['options' => [['label' => 'Never'], ['label' => 'Always']]]))->assertJsonStructure(['errors' => ['options']]);
        $post($this->likert('I enjoy meeting new people.', 'extraversion', ['options' => [['label' => 'Never', 'score_value' => 1, 'is_correct' => true], ['label' => 'Always', 'score_value' => 3]]]))->assertJsonStructure(['errors' => ['options']]);
        $post($this->likert('I enjoy meeting new people.', 'extraversion', ['options' => [['label' => 'Never', 'score_value' => 1, 'scale_key' => 'other'], ['label' => 'Always', 'score_value' => 3]]]))->assertJsonStructure(['errors' => ['options']]);
    }

    public function test_a_forced_choice_puts_the_scale_on_each_option(): void
    {
        $id = $this->newTest('provider');
        $sectionId = (int) $this->provider('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');
        $body = [
            'type' => 'forced_choice', 'text' => 'Which describes you better?',
            'options' => [['label' => 'I lead', 'score_value' => 1, 'scale_key' => 'dominance'], ['label' => 'I support', 'score_value' => 1, 'scale_key' => 'steadiness']],
        ];

        $this->provider('POST', "sections/{$sectionId}/questions", $body)->assertCreated()->assertJsonPath('data.scale_key', null)->assertJsonPath('data.options.1.scale_key', 'steadiness');
        $this->provider('POST', "sections/{$sectionId}/questions", [...$body, 'scale_key' => 'dominance'])->assertStatus(422)->assertJsonStructure(['errors' => ['scale_key']]);
        $this->provider('POST', "sections/{$sectionId}/questions", [...$body, 'options' => [['label' => 'I lead', 'score_value' => 1], ['label' => 'I support', 'score_value' => 1, 'scale_key' => 'steadiness']]])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['options']]);
    }

    public function test_an_objective_question_of_a_battery_belongs_to_a_scale_too(): void
    {
        $id = $this->newTest('provider');
        $sectionId = (int) $this->provider('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        $this->provider('POST', "sections/{$sectionId}/questions", $this->choice('Which is a prime number?', ['scale_key' => 'reasoning']))->assertCreated()->assertJsonPath('data.scale_key', 'reasoning');
        $this->provider('POST', "sections/{$sectionId}/questions", $this->choice('Which is a prime number?'))->assertStatus(422)->assertJsonStructure(['errors' => ['scale_key']]);
    }

    public function test_a_drawing_section_holds_drawing_questions_only(): void
    {
        $id = $this->newTest('provider');
        $drawing = (int) $this->provider('POST', "tests/{$id}/sections", ['title' => 'Draw a tree', 'format' => 'drawing', 'duration_seconds' => 900])->assertCreated()->assertJsonPath('data.format', 'drawing')->json('data.id');
        $questions = (int) $this->provider('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');

        $this->provider('POST', "sections/{$drawing}/questions", ['type' => 'drawing', 'text' => 'Draw a tree as you see it.'])->assertCreated()->assertJsonPath('data.points', 0)->assertJsonPath('data.scale_key', null);
        $this->provider('POST', "sections/{$drawing}/questions", $this->choice())->assertStatus(422)->assertJsonStructure(['errors' => ['type']]);
        $this->provider('POST', "sections/{$questions}/questions", ['type' => 'drawing', 'text' => 'Draw a tree as you see it.'])->assertStatus(422)->assertJsonStructure(['errors' => ['type']]);
    }

    public function test_a_speed_columns_section_lasts_as_long_as_its_columns_and_holds_no_questions(): void
    {
        $id = $this->newTest('provider');
        $settings = ['columns' => 10, 'digits_per_column' => 40, 'seconds_per_column' => 30];

        $section = (int) $this->provider('POST', "tests/{$id}/sections", ['title' => 'Columns', 'format' => 'speed_columns', 'settings' => $settings])
            ->assertCreated()->assertJsonPath('data.duration_seconds', 300)->assertJsonPath('data.settings.columns', 10)->json('data.id');

        $this->provider('POST', "sections/{$section}/questions", $this->likert())->assertStatus(422)->assertJsonStructure(['errors' => ['type']]);
        $this->provider('POST', "tests/{$id}/sections", ['title' => 'Missing', 'format' => 'speed_columns', 'settings' => ['columns' => 10]])->assertStatus(422)->assertJsonStructure(['errors' => ['settings']]);
        $this->provider('POST', "tests/{$id}/sections", ['title' => 'Too few digits', 'format' => 'speed_columns', 'settings' => [...$settings, 'digits_per_column' => 5]])->assertStatus(422)->assertJsonStructure(['errors' => ['settings']]);
        $this->provider('POST', "tests/{$id}/sections", ['title' => 'Too long', 'format' => 'speed_columns', 'settings' => [...$settings, 'columns' => 100, 'seconds_per_column' => 300]])->assertStatus(422)->assertJsonStructure(['errors' => ['settings']]);
        $this->provider('PUT', "sections/{$section}", ['title' => 'Columns', 'format' => 'speed_columns', 'settings' => [...$settings, 'columns' => 20]])->assertOk()->assertJsonPath('data.duration_seconds', 600);
    }

    // ----- norms and report template -----

    public function test_the_norm_table_is_replaced_as_a_whole(): void
    {
        $id = $this->newTest('provider');
        $this->sectionWithQuestion('provider', $id, $this->likert());

        $this->provider('GET', "tests/{$id}/norms")->assertOk()->assertJsonCount(0, 'data');
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => array_reverse($this->norms())])
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.raw_min', 0)->assertJsonPath('data.0.standard_score', 85.5)->assertJsonPath('data.1.band', 'Average');

        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 30, 'standard_score' => 100, 'band' => 'Any']]])->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, DB::table('assessment_norms')->where('test_id', $id)->count());

        $this->provider('PUT', "tests/{$id}/norms", ['norms' => []])->assertOk()->assertJsonCount(0, 'data');
        $this->provider('GET', "tests/{$id}/norms")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_norms_are_checked(): void
    {
        $id = $this->newTest('provider');
        $this->sectionWithQuestion('provider', $id, $this->likert());
        $put = fn (array $norms) => $this->provider('PUT', "tests/{$id}/norms", ['norms' => $norms])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-norms');
        $row = ['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 90, 'band' => 'Low'];

        $put([[...$row, 'scale_key' => 'unknown_scale']])->assertJsonStructure(['errors' => ['norms']]);
        $put([[...$row, 'raw_min' => 11, 'raw_max' => 10]]);
        $put([$row, [...$row, 'raw_min' => 10, 'raw_max' => 20]]);
        $put([[...$row, 'band' => str_repeat('b', 41)]]);
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [[...$row, 'standard_score' => 'high']]])->assertStatus(422);
        $this->provider('PUT', "tests/{$id}/norms", [])->assertStatus(422);
        $this->assertSame(0, DB::table('assessment_norms')->where('test_id', $id)->count());
    }

    public function test_ranges_of_different_scales_may_overlap(): void
    {
        $id = $this->newTest('provider');
        $section = (int) $this->provider('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", $this->likert())->assertCreated();
        $this->provider('POST', "sections/{$section}/questions", $this->likert('I like order and routine.', 'conscientiousness'))->assertCreated();

        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [...$this->norms(), ...$this->norms('conscientiousness')]])->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_a_skill_test_has_no_norms_or_report_template(): void
    {
        $id = $this->newTest('company');

        // A company admin reaches the provider routes as the author of the company's own tests, which are never batteries.
        $this->api('PUT', "/provider/tests/{$id}/norms", ['norms' => []], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:test-not-psychological');
        $this->api('PUT', "/provider/tests/{$id}/report-template", ['body' => 'Hello'], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:test-not-psychological');
    }

    public function test_the_report_template_accepts_only_known_placeholders_of_known_scales(): void
    {
        $id = $this->newTest('provider');
        $this->sectionWithQuestion('provider', $id, $this->likert());

        $this->provider('GET', "tests/{$id}/report-template")->assertOk()->assertJsonPath('data.body', null);

        $body = 'Report {{report_number}} for {{ candidate_name }} on {{test_title}}, signed {{signed_on}} by {{psychologist_name}}. '
            .'Raw {{scale:extraversion:raw}}, standard {{scale:extraversion:standard}}, band {{scale:extraversion:band}}.';
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => $body])->assertOk()->assertJsonPath('data.body', $body);
        $this->provider('GET', "tests/{$id}/report-template")->assertOk()->assertJsonPath('data.body', $body);

        foreach (['Hello {{secret}}', 'Hello {{scale:extraversion}}', 'Hello {{scale:extraversion:weight}}', 'Hello {{scale:missing:band}}', 'Hello {{scale:extraversion:band:more}}'] as $bad) {
            $this->provider('PUT', "tests/{$id}/report-template", ['body' => $bad])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-report-template')->assertJsonStructure(['errors' => ['body']]);
        }

        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'No placeholders at all'])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => ''])->assertStatus(422);
        $this->assertSame('No placeholders at all', DB::table('assessment_report_templates')->where('test_id', $id)->value('body'));
    }

    // ----- publishing and cloning -----

    public function test_a_battery_needs_a_norm_for_every_scale_and_a_report_template_to_be_published(): void
    {
        $id = $this->newTest('provider');
        $section = (int) $this->provider('POST', "tests/{$id}/sections", $this->section())->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", $this->likert())->assertCreated();
        $this->provider('POST', "sections/{$section}/questions", $this->likert('I like order and routine.', 'conscientiousness'))->assertCreated();
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => $this->norms()])->assertOk();

        $this->provider('POST', "tests/{$id}/publish")->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:test-incomplete')->assertJsonCount(2, 'errors.test');

        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [...$this->norms(), ...$this->norms('conscientiousness')]])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Your score is {{scale:extraversion:band}}.'])->assertOk();

        $this->provider('POST', "tests/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'published');
    }

    public function test_a_battery_of_drawings_needs_a_report_template_but_no_norms(): void
    {
        $id = $this->newTest('provider');
        $section = (int) $this->provider('POST', "tests/{$id}/sections", ['title' => 'Draw a tree', 'format' => 'drawing', 'duration_seconds' => 900])->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", ['type' => 'drawing', 'text' => 'Draw a tree as you see it.'])->assertCreated();

        $this->provider('POST', "tests/{$id}/publish")->assertStatus(422)->assertJsonCount(1, 'errors.test');
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'The drawing was reviewed.'])->assertOk();
        $this->provider('POST', "tests/{$id}/publish")->assertOk();
    }

    public function test_a_clone_of_a_battery_keeps_its_norms_and_template(): void
    {
        $id = $this->readyBattery();

        $copy = (int) $this->provider('POST', "tests/{$id}/clone")->assertCreated()->assertJsonPath('data.kind', 'psychological')->json('data.id');

        $this->provider('GET', "tests/{$copy}/norms")->assertOk()->assertJsonCount(2, 'data');
        $this->provider('GET', "tests/{$copy}/report-template")->assertOk()->assertJsonPath('data.body', 'Dear {{candidate_name}}, your extraversion is {{scale:extraversion:band}}.');
        $this->provider('POST', "tests/{$copy}/publish")->assertOk();
    }

    public function test_deleting_a_draft_battery_removes_its_content(): void
    {
        $id = $this->readyBattery();

        $this->provider('DELETE', "tests/{$id}")->assertNoContent();

        foreach (['assessment_sections', 'assessment_norms', 'assessment_report_templates'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(0, DB::table('assessment_questions')->count());
        $this->assertSame(0, DB::table('assessment_question_options')->count());
    }

    // ----- granting batteries to companies -----

    public function test_a_company_sees_no_batteries_until_the_setting_is_on(): void
    {
        $this->company('GET', 'batteries')->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:psychological-disabled');

        $this->overrideSetting('assessment.psychological_enabled', true);
        $this->company('GET', 'batteries')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_admin_grants_a_published_battery_and_the_company_uses_it(): void
    {
        $id = $this->readyBattery('Personality profile');
        $this->provider('PUT', 'questions/'.(int) DB::table('assessment_questions')->value('id'), $this->likert('I enjoy meeting new people.', 'extraversion', ['is_sample' => true]))->assertOk();

        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:battery-not-published');
        $this->provider('POST', "tests/{$id}/publish")->assertOk();

        $this->assessmentAdmin('GET', 'batteries')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Personality profile');
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertNoContent();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertNoContent();
        $this->assertSame(1, DB::table('assessment_company_batteries')->count());
        $this->assertSame($this->assessmentAdminId, (int) DB::table('assessment_company_batteries')->value('granted_by_admin_id'));
        $this->assessmentAdmin('GET', "companies/{$this->companyId}/batteries")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);

        // Granted, but the company may not use batteries yet.
        $this->company('GET', 'batteries')->assertForbidden();
        $this->company('GET', "catalog/{$id}")->assertNotFound();

        $this->overrideSetting('assessment.psychological_enabled', true);
        $this->company('GET', 'batteries')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Personality profile');
        $response = $this->company('GET', "catalog/{$id}", [], $this->recruiterToken)->assertOk()->assertJsonPath('data.kind', 'psychological')->assertJsonCount(1, 'data.samples');
        $this->assertStringNotContainsString('score_value', $response->getContent());
        $this->assertStringNotContainsString('extraversion', $response->getContent());

        // Another company has no grant.
        $this->overrideSetting('assessment.psychological_enabled', true, $this->otherCompanyId);
        $this->company('GET', 'batteries', [], $this->otherAdminToken)->assertOk()->assertJsonCount(0, 'data');
        $this->company('GET', "catalog/{$id}", [], $this->otherAdminToken)->assertNotFound();

        $this->assessmentAdmin('DELETE', "companies/{$this->companyId}/batteries/{$id}")->assertNoContent();
        $this->assessmentAdmin('DELETE', "companies/{$this->companyId}/batteries/{$id}")->assertNoContent();
        $this->company('GET', 'batteries')->assertOk()->assertJsonCount(0, 'data');
        $this->company('GET', "catalog/{$id}")->assertNotFound();
    }

    public function test_a_suspended_provider_takes_its_batteries_out_of_the_catalogue(): void
    {
        $id = $this->readyBattery();
        $this->provider('POST', "tests/{$id}/publish")->assertOk();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertNoContent();
        $this->overrideSetting('assessment.psychological_enabled', true);
        $this->company('GET', 'batteries')->assertOk()->assertJsonCount(1, 'data');

        DB::table('assessment_providers')->where('id', $this->providerId)->update(['status' => 'suspended']);

        $this->company('GET', 'batteries')->assertOk()->assertJsonCount(0, 'data');
        $this->company('GET', "catalog/{$id}")->assertNotFound();
        $this->assessmentAdmin('GET', 'batteries')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_a_real_battery_of_a_real_company_is_granted(): void
    {
        $skill = $this->newTest('library');
        $this->sectionWithQuestion('library', $skill);
        $this->library('POST', "tests/{$skill}/publish")->assertOk();
        $battery = $this->readyBattery();
        $this->provider('POST', "tests/{$battery}/publish")->assertOk();

        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $skill])->assertNotFound();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => 999_999])->assertNotFound();
        $this->assessmentAdmin('POST', 'companies/999999/batteries', ['test_id' => $battery])->assertNotFound();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", [])->assertStatus(422);
        $this->assessmentAdmin('GET', 'companies/999999/batteries')->assertNotFound();
    }

    public function test_granting_needs_the_provider_permission(): void
    {
        $admin = AdminModel::query()->create(['name' => 'Library only', 'email' => 'library@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['assessment_library.manage']);
        $token = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;

        $this->assessmentAdmin('GET', 'batteries', [], $token)->assertForbidden();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => 1], $token)->assertForbidden();
        $this->assessmentAdmin('DELETE', "companies/{$this->companyId}/batteries/1", [], $token)->assertForbidden();
    }
}
