<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Facades\DB;

class ApplicationGroupTest extends CompanyTestCase
{
    private function group(int $company, string $name, bool $deleteable = true): int
    {
        return DB::table('application_groups')->insertGetId(['company_id' => $company, 'name' => $name, 'is_deleteable' => $deleteable, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_requires_company_auth(): void
    {
        $this->getJson('/api/company/application/group')->assertUnauthorized();
        $this->postJson('/api/company/application/group', ['name' => 'x'])->assertUnauthorized();
        $this->putJson('/api/company/application/group/1', ['name' => 'x'])->assertUnauthorized();
        $this->deleteJson('/api/company/application/group/1')->assertUnauthorized();
    }

    public function test_list_create_and_update(): void
    {
        $made = $this->actingAsCompany();
        $existing = $this->group($made['company'], 'Interview');
        $this->group($this->makeCompany()['company'], 'Other company');

        $this->getJson('/api/company/application/group')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $existing);

        $group = $this->postJson('/api/company/application/group', ['name' => 'Offer'])->assertOk()
            ->assertJsonPath('data.message', 'Group Created Successfully')
            ->assertJsonPath('data.group.name', 'Offer')->json('data.group');

        $this->putJson("/api/company/application/group/{$group['id']}", ['name' => 'Offered'])->assertOk()
            ->assertJsonPath('data.group.name', 'Offered')->assertJsonPath('data.message', 'Group Updated Successfully');
    }

    public function test_validation(): void
    {
        $made = $this->actingAsCompany();
        $id = $this->group($made['company'], 'G');

        $this->postJson('/api/company/application/group', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson("/api/company/application/group/{$id}", ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_ownership_is_enforced(): void
    {
        $foreign = $this->group($this->makeCompany()['company'], 'Foreign');
        $this->actingAsCompany();

        $this->putJson("/api/company/application/group/{$foreign}", ['name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/company/application/group/{$foreign}")->assertNotFound();
        $this->assertDatabaseHas('application_groups', ['id' => $foreign, 'name' => 'Foreign']);
    }

    public function test_delete_moves_applications_to_default_group(): void
    {
        $made = $this->actingAsCompany();
        $default = $this->group($made['company'], 'No Group', false);
        $group = $this->group($made['company'], 'Temp');
        $candidate = $this->makeCandidate();
        $app = DB::table('applied_jobs')->insertGetId(['candidate_id' => $candidate['candidate'], 'application_group_id' => $group, 'created_at' => now(), 'updated_at' => now()]);

        $this->deleteJson("/api/company/application/group/{$group}")->assertOk()
            ->assertJsonPath('data.success', 'Group Deleted Successfully');

        $this->assertDatabaseMissing('application_groups', ['id' => $group]);
        $this->assertSame($default, (int) DB::table('applied_jobs')->where('id', $app)->value('application_group_id'));
    }

    public function test_default_group_cannot_be_deleted(): void
    {
        $made = $this->actingAsCompany();
        $default = $this->group($made['company'], 'No Group', false);

        $this->deleteJson("/api/company/application/group/{$default}")->assertStatus(400)
            ->assertExactJson(['error' => 'Group is not deletable']);
        $this->assertDatabaseHas('application_groups', ['id' => $default]);
    }
}
