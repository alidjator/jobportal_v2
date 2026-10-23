<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

final class CompanyStageTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
        $this->app->make(DefaultStageProvisioner::class)->provision($this->companyId);
        $this->app->make(DefaultStageProvisioner::class)->provision($this->otherCompanyId);
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /** @return list<string> the stage names of a company in their stored order */
    private function names(?int $companyId = null): array
    {
        return DB::table('recruitment_stages')->where('company_id', $companyId ?? $this->companyId)->orderBy('position')->pluck('name')->all();
    }

    /** @return list<int> */
    private function positions(): array
    {
        return DB::table('recruitment_stages')->where('company_id', $this->companyId)->orderBy('position')->pluck('position')->map(fn ($p) => (int) $p)->all();
    }

    private function stageId(string $name, ?int $companyId = null): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $companyId ?? $this->companyId)->where('name', $name)->value('id');
    }

    public function test_the_stages_need_a_signed_in_company_member(): void
    {
        $this->getJson('/api/v1/company/stages')->assertStatus(401);

        $stranger = $this->jobMember($this->companyId, 'X', 'x@example.test', 'recruiter');
        DB::table('company_members')->where('user_id', $stranger)->delete();
        $this->api('GET', '/company/stages', token: $this->tokenFor($stranger))->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
    }

    public function test_admins_and_recruiters_read_the_stages_in_order(): void
    {
        foreach ([$this->adminToken, $this->recruiterToken] as $token) {
            $response = $this->api('GET', '/company/stages', token: $token)->assertOk();

            $this->assertSame(['applied', 'screening', 'assessment', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'], array_column($response->json('data'), 'type'));
            $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], array_column($response->json('data'), 'position'));
            $this->assertSame('Dilamar', $response->json('data.0.name'));
            $this->assertTrue($response->json('data.0.is_default'));
        }
    }

    public function test_one_company_never_sees_the_stages_of_another(): void
    {
        $response = $this->api('GET', '/company/stages', token: $this->otherAdminToken)->assertOk();

        $this->assertCount(8, $response->json('data'));
        $this->assertNotContains($this->stageId('Dilamar'), array_column($response->json('data'), 'id'));
    }

    public function test_a_suspended_company_cannot_use_its_stages(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->api('GET', '/company/stages')->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-suspended');
        $this->api('POST', '/company/stages', ['name' => 'Panel', 'type' => 'interview'])->assertStatus(403);
    }

    public function test_changes_are_for_company_admins_only(): void
    {
        $id = $this->stageId('Penyaringan');

        foreach ([
            ['POST', '/company/stages', ['name' => 'Panel', 'type' => 'interview']],
            ['PATCH', "/company/stages/{$id}", ['name' => 'Phone']],
            ['DELETE', "/company/stages/{$id}", []],
            ['PUT', '/company/stages/order', ['stage_ids' => [1]]],
        ] as [$method, $uri, $body]) {
            $this->api($method, $uri, $body, $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        }

        $this->assertCount(8, $this->names());
    }

    public function test_an_extra_stage_goes_before_the_terminal_stages(): void
    {
        $response = $this->api('POST', '/company/stages', ['name' => 'Technical Interview', 'type' => 'interview'])->assertCreated();

        $response->assertJsonPath('data.name', 'Technical Interview')->assertJsonPath('data.type', 'interview')
            ->assertJsonPath('data.position', 7)->assertJsonPath('data.is_default', false);
        $this->assertSame(['Dilamar', 'Penyaringan', 'Asesmen', 'Wawancara', 'Penawaran', 'Diterima', 'Technical Interview', 'Ditolak', 'Mengundurkan diri'], $this->names());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], $this->positions());
    }

    public function test_only_screening_assessment_interview_and_offer_may_have_extra_stages(): void
    {
        foreach (['applied', 'hired', 'rejected', 'withdrawn'] as $type) {
            $this->api('POST', '/company/stages', ['name' => "Extra {$type}", 'type' => $type])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:stage-type-not-allowed');
        }

        $this->api('POST', '/company/stages', ['name' => 'Extra', 'type' => 'assessment'])->assertCreated();
        $this->assertCount(9, $this->names());
    }

    public function test_a_stage_name_is_required_and_at_most_sixty_characters(): void
    {
        $this->api('POST', '/company/stages', ['type' => 'offer'])->assertStatus(422);
        $this->api('POST', '/company/stages', ['name' => str_repeat('a', 61), 'type' => 'offer'])->assertStatus(422);
        $this->api('POST', '/company/stages', ['name' => str_repeat('a', 60), 'type' => 'offer'])->assertCreated();
    }

    public function test_names_are_unique_in_the_company_whatever_the_letter_case(): void
    {
        $this->api('POST', '/company/stages', ['name' => 'wawancara', 'type' => 'interview'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:stage-name-taken');
        $this->api('POST', '/company/stages', ['name' => 'Panel', 'type' => 'interview'])->assertCreated();
        $this->api('POST', '/company/stages', ['name' => 'PANEL', 'type' => 'offer'])->assertStatus(409);
        $this->assertCount(9, $this->names());

        // Another company may use the same name.
        $this->api('POST', '/company/stages', ['name' => 'Panel', 'type' => 'interview'], $this->otherAdminToken)->assertCreated();
    }

    public function test_the_company_cannot_pass_its_stage_limit(): void
    {
        foreach (['One', 'Two', 'Three'] as $name) {
            $this->api('POST', '/company/stages', ['name' => $name, 'type' => 'screening'])->assertCreated();
        }

        $this->api('POST', '/company/stages', ['name' => 'Four', 'type' => 'screening'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:stage-limit-reached');
        $this->assertCount(11, $this->names());
    }

    public function test_a_company_override_of_the_limit_applies_to_that_company_only(): void
    {
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::MAX_STAGES, $this->companyId, 9, null);

        $this->api('POST', '/company/stages', ['name' => 'One', 'type' => 'screening'])->assertCreated();
        $this->api('POST', '/company/stages', ['name' => 'Two', 'type' => 'screening'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:stage-limit-reached');

        foreach (['One', 'Two', 'Three'] as $name) {
            $this->api('POST', '/company/stages', ['name' => $name, 'type' => 'screening'], $this->otherAdminToken)->assertCreated();
        }
    }

    public function test_any_stage_can_be_renamed_and_keeps_its_place(): void
    {
        $id = $this->stageId('Wawancara');

        $this->api('PATCH', "/company/stages/{$id}", ['name' => '  Wawancara User  '])->assertOk()
            ->assertJsonPath('data.name', 'Wawancara User')->assertJsonPath('data.type', 'interview')->assertJsonPath('data.position', 4)->assertJsonPath('data.is_default', true);

        $this->assertSame(['Dilamar', 'Penyaringan', 'Asesmen', 'Wawancara User', 'Penawaran', 'Diterima', 'Ditolak', 'Mengundurkan diri'], $this->names());
    }

    public function test_a_rename_cannot_take_the_name_of_another_stage_but_may_change_only_the_case(): void
    {
        $id = $this->stageId('Wawancara');

        $this->api('PATCH', "/company/stages/{$id}", ['name' => 'PENAWARAN'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:stage-name-taken');
        $this->api('PATCH', "/company/stages/{$id}", ['name' => 'WAWANCARA'])->assertOk()->assertJsonPath('data.name', 'WAWANCARA');
    }

    public function test_a_stage_of_another_company_does_not_exist(): void
    {
        $foreign = $this->stageId('Wawancara', $this->otherCompanyId);

        $this->api('PATCH', "/company/stages/{$foreign}", ['name' => 'Hacked'])->assertStatus(404);
        $this->api('DELETE', "/company/stages/{$foreign}")->assertStatus(404);
        $this->assertSame('Wawancara', DB::table('recruitment_stages')->where('id', $foreign)->value('name'));
    }

    public function test_an_extra_stage_can_be_deleted_and_the_positions_close_up(): void
    {
        $id = (int) $this->api('POST', '/company/stages', ['name' => 'Panel', 'type' => 'interview'])->json('data.id');

        $this->api('DELETE', "/company/stages/{$id}")->assertNoContent();

        $this->assertSame(['Dilamar', 'Penyaringan', 'Asesmen', 'Wawancara', 'Penawaran', 'Diterima', 'Ditolak', 'Mengundurkan diri'], $this->names());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $this->positions());
    }

    public function test_a_default_stage_cannot_be_deleted(): void
    {
        $this->api('DELETE', '/company/stages/'.$this->stageId('Penawaran'))->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:default-stage-cannot-be-deleted');
        $this->assertCount(8, $this->names());
    }

    public function test_the_whole_order_can_be_replaced(): void
    {
        $panel = (int) $this->api('POST', '/company/stages', ['name' => 'Panel', 'type' => 'interview'])->json('data.id');
        $ids = fn (array $names): array => array_map(fn (string $name): int => $name === 'Panel' ? $panel : $this->stageId($name), $names);

        $wanted = ['Dilamar', 'Panel', 'Penawaran', 'Penyaringan', 'Asesmen', 'Wawancara', 'Diterima', 'Mengundurkan diri', 'Ditolak'];
        $response = $this->api('PUT', '/company/stages/order', ['stage_ids' => $ids($wanted)])->assertOk();

        $this->assertSame($wanted, array_column($response->json('data'), 'name'));
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], array_column($response->json('data'), 'position'));
        $this->assertSame($wanted, $this->names());
    }

    public function test_the_order_must_name_every_stage_once(): void
    {
        $ids = DB::table('recruitment_stages')->where('company_id', $this->companyId)->orderBy('position')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $foreign = $this->stageId('Dilamar', $this->otherCompanyId);

        $this->api('PUT', '/company/stages/order', ['stage_ids' => array_slice($ids, 0, 6)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-stage-order');
        $this->api('PUT', '/company/stages/order', ['stage_ids' => [...array_slice($ids, 0, 6), $foreign]])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-stage-order');
        $this->api('PUT', '/company/stages/order', ['stage_ids' => [...$ids, $ids[0]]])->assertStatus(422);
        $this->api('PUT', '/company/stages/order', [])->assertStatus(422);
        $this->assertSame(['Dilamar', 'Penyaringan', 'Asesmen', 'Wawancara', 'Penawaran', 'Diterima', 'Ditolak', 'Mengundurkan diri'], $this->names());
    }

    public function test_applied_must_stay_first_and_the_terminal_stages_last(): void
    {
        $id = fn (string $name): int => $this->stageId($name);

        $appliedNotFirst = [$id('Penyaringan'), $id('Dilamar'), $id('Asesmen'), $id('Wawancara'), $id('Penawaran'), $id('Diterima'), $id('Ditolak'), $id('Mengundurkan diri')];
        $terminalInTheMiddle = [$id('Dilamar'), $id('Penyaringan'), $id('Ditolak'), $id('Asesmen'), $id('Wawancara'), $id('Penawaran'), $id('Diterima'), $id('Mengundurkan diri')];

        $this->api('PUT', '/company/stages/order', ['stage_ids' => $appliedNotFirst])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-stage-order')->assertJsonStructure(['errors' => ['stage_ids']]);
        $this->api('PUT', '/company/stages/order', ['stage_ids' => $terminalInTheMiddle])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-stage-order');
        $this->assertSame(['Dilamar', 'Penyaringan', 'Asesmen', 'Wawancara', 'Penawaran', 'Diterima', 'Ditolak', 'Mengundurkan diri'], $this->names());
    }
}
