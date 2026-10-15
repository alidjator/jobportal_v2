<?php

namespace Tests\Feature\ReferenceData;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\FeatureTestCase;

final class AdminMasterTest extends FeatureTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->token = $this->adminToken(['reference_data.manage']);
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'admin@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Admin', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    private function asAdmin(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, "/api/v1/admin/{$uri}", $data);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return ['name' => ['id' => 'Teknologi Informasi', 'en' => 'Information Technology'], 'sort_order' => 5, 'is_active' => true, ...$overrides];
    }

    public function test_management_needs_the_reference_data_permission(): void
    {
        $this->getJson('/api/v1/admin/industries')->assertStatus(401);

        $this->asAdmin('GET', 'industries', token: $this->adminToken(['faqs.manage'], 'faq@example.test'))->assertStatus(403);
        $this->asAdmin('POST', 'industries', $this->payload(), $this->adminToken(['faqs.manage'], 'other@example.test'))->assertStatus(403);
    }

    public function test_an_item_is_created_with_a_slug_from_the_english_name(): void
    {
        $this->asAdmin('POST', 'industries', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'information-technology')
            ->assertJsonPath('data.name', ['id' => 'Teknologi Informasi', 'en' => 'Information Technology'])
            ->assertJsonPath('data.sort_order', 5)
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(1, DB::table('reference_industries')->count());
    }

    public function test_a_taken_slug_gets_a_number_and_the_original_keeps_its_slug(): void
    {
        $first = $this->asAdmin('POST', 'industries', $this->payload())->assertCreated()->json('data.slug');
        $second = $this->asAdmin('POST', 'industries', $this->payload(['name' => ['id' => 'Lain', 'en' => 'Information  Technology!']]))->assertCreated()->json('data.slug');
        $third = $this->asAdmin('POST', 'industries', $this->payload())->assertCreated()->json('data.slug');

        $this->assertSame(['information-technology', 'information-technology-2', 'information-technology-3'], [$first, $second, $third]);
    }

    public function test_the_slug_does_not_change_when_the_name_changes(): void
    {
        $id = $this->asAdmin('POST', 'industries', $this->payload())->assertCreated()->json('data.id');

        $this->asAdmin('PUT', "industries/{$id}", $this->payload(['name' => ['id' => 'Baru', 'en' => 'Brand New'], 'sort_order' => 9]))
            ->assertOk()
            ->assertJsonPath('data.slug', 'information-technology')
            ->assertJsonPath('data.name.en', 'Brand New')
            ->assertJsonPath('data.sort_order', 9);
    }

    public function test_both_names_are_required_and_the_defaults_apply(): void
    {
        $this->asAdmin('POST', 'industries', ['name' => ['id' => 'Saja Indonesia']])->assertStatus(422)->assertJsonValidationErrors(['name.en'], 'errors');
        $this->asAdmin('POST', 'industries', $this->payload(['sort_order' => -1]))->assertStatus(422);

        $this->asAdmin('POST', 'industries', ['name' => ['id' => 'Satu', 'en' => 'One']])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 0)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_a_retired_item_disappears_from_the_public_list_but_stays_in_the_admin_list(): void
    {
        $id = $this->asAdmin('POST', 'industries', $this->payload())->assertCreated()->json('data.id');
        $this->getJson('/api/v1/industries')->assertJsonCount(1, 'data');

        $this->asAdmin('PUT', "industries/{$id}", $this->payload(['is_active' => false]))->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/industries')->assertJsonCount(0, 'data');
        $this->asAdmin('GET', 'industries')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_active', false);
    }

    public function test_updating_an_unknown_item_is_a_404(): void
    {
        $this->asAdmin('PUT', 'industries/999999', $this->payload())->assertStatus(404)->assertJsonPath('type', 'urn:onesis:error:not-found');
    }

    public function test_extra_columns_follow_the_definition_of_the_master(): void
    {
        $this->asAdmin('POST', 'education-levels', $this->payload())->assertStatus(422)->assertJsonValidationErrors(['rank'], 'errors');
        $this->asAdmin('POST', 'education-levels', $this->payload(['rank' => 70000]))->assertStatus(422);
        $this->asAdmin('POST', 'education-levels', $this->payload(['rank' => 4]))->assertCreated()->assertJsonPath('data.rank', 4);

        $this->asAdmin('POST', 'skills', $this->payload())->assertStatus(422)->assertJsonValidationErrors(['kind'], 'errors');
        $this->asAdmin('POST', 'skills', $this->payload(['kind' => 'medium']))->assertStatus(422);
        $this->asAdmin('POST', 'skills', $this->payload(['kind' => 'hard']))->assertCreated()->assertJsonPath('data.kind', 'hard');

        $this->asAdmin('POST', 'experience-levels', $this->payload())->assertCreated()->assertJsonPath('data.min_years', null);
        $this->asAdmin('POST', 'team-sizes', $this->payload(['min_size' => 1, 'max_size' => 10]))->assertCreated()->assertJsonPath('data.max_size', 10);
    }

    public function test_a_language_code_must_be_unique_also_on_update(): void
    {
        $english = $this->asAdmin('POST', 'languages', $this->payload(['name' => ['id' => 'Inggris', 'en' => 'English'], 'code' => 'en']))->assertCreated()->json('data.id');
        $other = $this->asAdmin('POST', 'languages', $this->payload(['name' => ['id' => 'Jerman', 'en' => 'German'], 'code' => 'de']))->assertCreated()->json('data.id');

        $this->asAdmin('POST', 'languages', $this->payload(['name' => ['id' => 'Lain', 'en' => 'Another'], 'code' => 'en']))
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:reference-value-taken');
        $this->asAdmin('PUT', "languages/{$other}", $this->payload(['name' => ['id' => 'Jerman', 'en' => 'German'], 'code' => 'en']))->assertStatus(409);

        $this->asAdmin('PUT', "languages/{$english}", $this->payload(['name' => ['id' => 'Inggris', 'en' => 'English'], 'code' => 'en']))->assertOk();
        $this->asAdmin('POST', 'languages', $this->payload(['code' => str_repeat('x', 11)]))->assertStatus(422);
    }

    public function test_the_admin_list_follows_the_same_pagination_rules_as_the_public_one(): void
    {
        foreach (range(1, 3) as $number) {
            $this->asAdmin('POST', 'skills', $this->payload(['name' => ['id' => "Skill {$number}", 'en' => "Skill {$number}"], 'kind' => 'hard', 'is_active' => $number !== 2]))->assertCreated();
        }

        $this->asAdmin('GET', 'skills?per_page=2')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(2, 'data');
        $this->asAdmin('GET', 'skills?per_page=101')->assertStatus(422);
        $this->getJson('/api/v1/skills')->assertJsonPath('meta.total', 2);
    }

    public function test_tags_cannot_be_managed_yet_and_unknown_masters_do_not_exist(): void
    {
        $this->asAdmin('POST', 'tags', $this->payload())->assertStatus(404);
        $this->asAdmin('GET', 'tags')->assertStatus(404);
        $this->asAdmin('GET', 'unicorns')->assertStatus(404);
    }

    public function test_every_other_master_can_be_created(): void
    {
        $extras = ['education-levels' => ['rank' => 1], 'skills' => ['kind' => 'soft'], 'languages' => ['code' => 'zz']];

        foreach (['job-categories', 'job-roles', 'job-types', 'job-levels', 'work-times', 'experience-levels', 'education-levels', 'salary-periods', 'skills',
            'benefits', 'industries', 'organization-types', 'team-sizes', 'religions', 'nationalities', 'languages'] as $key) {
            $this->asAdmin('POST', $key, $this->payload($extras[$key] ?? []))->assertCreated();
        }

        $this->assertSame('active', DB::table('reference_benefits')->value('status'));
    }

    public function test_a_created_benefit_is_a_global_active_one_and_is_listed_publicly(): void
    {
        $this->asAdmin('POST', 'benefits', $this->payload(['name' => ['id' => 'Asuransi', 'en' => 'Insurance']]))->assertCreated();

        $this->getJson('/api/v1/benefits')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'insurance');
    }
}
