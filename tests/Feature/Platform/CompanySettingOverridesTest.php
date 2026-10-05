<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Settings\SettingDefinition;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Tests\FeatureTestCase;

final class CompanySettingOverridesTest extends FeatureTestCase
{
    private string $token;

    private int $companyId;

    private int $otherCompanyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(SettingsCatalog::class)->add(
            SettingDefinition::integer('probe.limit', 5, 1, 50, 'How many things are allowed.', overridable: true),
            SettingDefinition::boolean('probe.enabled', true, 'A switch.', overridable: true),
            SettingDefinition::choices('probe.templates', ['classic'], ['classic', 'modern', 'compact'], 'Active templates.', minItems: 1, overridable: true),
            SettingDefinition::integer('probe.global_only', 10, 1, 100, 'Cannot be overridden per company.'),
        );

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->token = $this->adminToken(['settings.manage']);
        $this->companyId = $this->company('pt-maju');
        $this->otherCompanyId = $this->company('pt-lain');
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'admin@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Admin', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    private function company(string $slug): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => $slug, 'slug' => $slug, 'status' => 'active', 'industry_id' => 1, 'organization_type_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function api(string $method, string $suffix, array $body = [], ?int $company = null, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/admin/companies/'.($company ?? $this->companyId).'/settings'.$suffix, $body);
    }

    private function effective(string $key, ?int $company = null): mixed
    {
        $settings = $this->app->make(Settings::class);

        return match ($key) {
            'probe.enabled' => $settings->boolean($key, $company),
            'probe.templates' => $settings->choices($key, $company),
            default => $settings->integer($key, $company),
        };
    }

    /** @return list<object> */
    private function audit(string $action): array
    {
        return DB::table('platform_audit_logs')->where('action', $action)->orderBy('id')->get()->all();
    }

    // ----- access -----

    public function test_the_routes_need_an_admin_with_the_settings_permission(): void
    {
        $this->flushHeaders();
        $this->getJson("/api/v1/admin/companies/{$this->companyId}/settings")->assertStatus(401);
        $this->putJson("/api/v1/admin/companies/{$this->companyId}/settings/probe.limit", ['value' => 9])->assertStatus(401);
        $this->deleteJson("/api/v1/admin/companies/{$this->companyId}/settings/probe.limit")->assertStatus(401);

        $weak = $this->adminToken(['companies.manage'], 'weak@example.test');
        $this->api('GET', '', token: $weak)->assertStatus(403);
        $this->api('PUT', '/probe.limit', ['value' => 9], token: $weak)->assertStatus(403);
        $this->api('DELETE', '/probe.limit', token: $weak)->assertStatus(403);
        $this->assertSame(5, $this->effective('probe.limit', $this->companyId));
    }

    // ----- listing -----

    public function test_the_list_holds_only_the_overridable_settings_with_global_and_own_values(): void
    {
        $response = $this->api('GET', '')->assertOk();

        $keys = array_column($response->json('data'), 'key');
        $this->assertSame(['probe.enabled', 'probe.limit', 'probe.templates'], array_values(array_filter($keys, fn (string $key): bool => str_starts_with($key, 'probe.'))));
        $this->assertNotContains('probe.global_only', $keys);

        $limit = collect($response->json('data'))->firstWhere('key', 'probe.limit');
        $this->assertSame('integer', $limit['type']);
        $this->assertSame(5, $limit['default']);
        $this->assertSame(5, $limit['global_value']);
        $this->assertNull($limit['override']);
        $this->assertFalse($limit['has_override']);
        $this->assertSame(5, $limit['value']);
        $this->assertSame(['min' => 1, 'max' => 50], $limit['constraints']);
    }

    public function test_the_list_reflects_a_changed_global_value_and_the_companys_override(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal('probe.limit', 12, null);
        $store->setCompanyOverride('probe.limit', $this->companyId, 30, null);

        $mine = collect($this->api('GET', '')->json('data'))->firstWhere('key', 'probe.limit');
        $this->assertSame([12, 30, true, 30], [$mine['global_value'], $mine['override'], $mine['has_override'], $mine['value']]);

        $other = collect($this->api('GET', '', company: $this->otherCompanyId)->json('data'))->firstWhere('key', 'probe.limit');
        $this->assertSame([12, null, false, 12], [$other['global_value'], $other['override'], $other['has_override'], $other['value']]);
    }

    public function test_an_unknown_company_is_not_found_on_every_route(): void
    {
        $this->api('GET', '', company: 999999)->assertNotFound();
        $this->api('PUT', '/probe.limit', ['value' => 9], company: 999999)->assertNotFound();
        $this->api('DELETE', '/probe.limit', company: 999999)->assertNotFound();
        $this->assertSame(0, DB::table('platform_setting_overrides')->count());
    }

    // ----- setting -----

    public function test_an_override_applies_to_that_company_only(): void
    {
        $this->api('PUT', '/probe.limit', ['value' => 9])->assertOk()
            ->assertJsonPath('data.key', 'probe.limit')
            ->assertJsonPath('data.global_value', 5)
            ->assertJsonPath('data.override', 9)
            ->assertJsonPath('data.has_override', true)
            ->assertJsonPath('data.value', 9);

        $this->assertSame(9, $this->effective('probe.limit', $this->companyId));
        $this->assertSame(5, $this->effective('probe.limit', $this->otherCompanyId));
        $this->assertSame(5, $this->effective('probe.limit'));
        $this->assertSame(1, DB::table('platform_setting_overrides')->count());
        $row = DB::table('platform_setting_overrides')->first();
        $this->assertSame([$this->companyId, 'company', 'probe.limit'], [(int) $row->scope_id, $row->scope_type, $row->key]);
        $this->assertNotNull($row->updated_by_admin_id);
    }

    public function test_the_override_survives_a_change_of_the_global_value_and_can_be_changed_again(): void
    {
        $this->api('PUT', '/probe.limit', ['value' => 9])->assertOk();
        $this->app->make(SettingsStore::class)->setGlobal('probe.limit', 12, null);

        $this->assertSame(9, $this->effective('probe.limit', $this->companyId));

        $this->api('PUT', '/probe.limit', ['value' => 20])->assertOk()->assertJsonPath('data.value', 20)->assertJsonPath('data.global_value', 12);
        $this->assertSame(20, $this->effective('probe.limit', $this->companyId));
        $this->assertSame(1, DB::table('platform_setting_overrides')->count());
    }

    public function test_every_type_can_be_overridden(): void
    {
        $this->api('PUT', '/probe.enabled', ['value' => false])->assertOk()->assertJsonPath('data.value', false);
        $this->api('PUT', '/probe.templates', ['value' => ['modern', 'compact', 'modern']])->assertOk()->assertJsonPath('data.value', ['modern', 'compact']);

        $this->assertFalse($this->effective('probe.enabled', $this->companyId));
        $this->assertTrue($this->effective('probe.enabled', $this->otherCompanyId));
        $this->assertSame(['modern', 'compact'], $this->effective('probe.templates', $this->companyId));
    }

    public function test_an_explicit_value_equal_to_the_global_one_is_still_stored(): void
    {
        $this->api('PUT', '/probe.limit', ['value' => 5])->assertOk()->assertJsonPath('data.has_override', true);

        $this->app->make(SettingsStore::class)->setGlobal('probe.limit', 12, null);

        $this->assertSame(5, $this->effective('probe.limit', $this->companyId));
    }

    public function test_an_invalid_value_a_missing_value_an_unknown_key_and_a_global_only_setting_are_refused(): void
    {
        $this->api('PUT', '/probe.limit', ['value' => 0])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-setting-value');
        $this->api('PUT', '/probe.limit', ['value' => 51])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-setting-value');
        $this->api('PUT', '/probe.limit', ['value' => 'many'])->assertStatus(422);
        $this->api('PUT', '/probe.templates', ['value' => []])->assertStatus(422);
        $this->api('PUT', '/probe.limit', [])->assertStatus(422);
        $this->api('PUT', '/probe.nothing', ['value' => 1])->assertNotFound();
        $this->api('PUT', '/probe.global_only', ['value' => 20])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:setting-not-overridable');

        $this->assertSame(0, DB::table('platform_setting_overrides')->count());
        $this->assertSame([], $this->audit('settings.updated'));
    }

    // ----- audit of setting -----

    public function test_a_change_is_audited_with_the_company_and_the_old_and_new_value(): void
    {
        $this->api('PUT', '/probe.limit', ['value' => 9])->assertOk();
        $this->api('PUT', '/probe.limit', ['value' => 20])->assertOk();

        $entries = $this->audit('settings.updated');
        $this->assertCount(2, $entries);
        $this->assertSame('admin', $entries[0]->actor_type);
        $this->assertSame('company', $entries[0]->subject_type);
        $this->assertSame($this->companyId, (int) $entries[0]->subject_id);
        $this->assertSame(['key' => 'probe.limit', 'old' => 5, 'new' => 9], json_decode((string) $entries[0]->metadata, true));
        $this->assertSame(['key' => 'probe.limit', 'old' => 9, 'new' => 20], json_decode((string) $entries[1]->metadata, true));
        $this->assertNotNull($entries[0]->ip_address);
    }

    public function test_setting_the_value_the_company_already_has_changes_and_audits_nothing(): void
    {
        $this->api('PUT', '/probe.limit', ['value' => 9])->assertOk();
        $before = DB::table('platform_setting_overrides')->value('updated_at');

        $this->api('PUT', '/probe.limit', ['value' => 9])->assertOk()->assertJsonPath('data.override', 9);

        $this->assertCount(1, $this->audit('settings.updated'));
        $this->assertSame($before, DB::table('platform_setting_overrides')->value('updated_at'));
    }

    // ----- resetting -----

    public function test_resetting_takes_the_override_away_so_the_global_value_applies_again(): void
    {
        $this->api('PUT', '/probe.limit', ['value' => 9])->assertOk();
        $this->api('PUT', '/probe.limit', ['value' => 20], company: $this->otherCompanyId)->assertOk();
        $this->app->make(SettingsStore::class)->setGlobal('probe.limit', 12, null);

        $this->api('DELETE', '/probe.limit')->assertOk()
            ->assertJsonPath('data.override', null)
            ->assertJsonPath('data.has_override', false)
            ->assertJsonPath('data.value', 12);

        $this->assertSame(12, $this->effective('probe.limit', $this->companyId));
        $this->assertSame(20, $this->effective('probe.limit', $this->otherCompanyId));
        $this->assertSame(1, DB::table('platform_setting_overrides')->count());
    }

    public function test_resetting_is_audited_once_and_a_second_reset_does_nothing(): void
    {
        $this->api('PUT', '/probe.limit', ['value' => 9])->assertOk();

        $this->api('DELETE', '/probe.limit')->assertOk();
        $this->api('DELETE', '/probe.limit')->assertOk()->assertJsonPath('data.has_override', false);

        $entries = $this->audit('settings.reset');
        $this->assertCount(1, $entries);
        $this->assertSame('company', $entries[0]->subject_type);
        $this->assertSame($this->companyId, (int) $entries[0]->subject_id);
        $this->assertSame(['key' => 'probe.limit', 'old' => 9], json_decode((string) $entries[0]->metadata, true));
    }

    public function test_resetting_a_global_only_or_unknown_setting_is_refused(): void
    {
        $this->api('DELETE', '/probe.global_only')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:setting-not-overridable');
        $this->api('DELETE', '/probe.nothing')->assertNotFound();
        $this->assertSame([], $this->audit('settings.reset'));
    }

    // ----- the global settings stay as they were -----

    public function test_the_global_list_does_not_show_company_overrides_and_global_changes_do_not_touch_them(): void
    {
        $this->api('PUT', '/probe.limit', ['value' => 9])->assertOk();

        $this->app['auth']->forgetGuards();
        $global = collect($this->withToken($this->token)->getJson('/api/v1/admin/settings')->json('data'))->firstWhere('key', 'probe.limit');
        $this->assertSame(5, $global['value']);
        $this->assertTrue($global['is_default']);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->deleteJson('/api/v1/admin/settings/probe.limit')->assertOk();
        $this->assertSame(9, $this->effective('probe.limit', $this->companyId));
    }
}
