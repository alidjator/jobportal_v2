<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use LogicException;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Settings\SettingDefinition;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Tests\FeatureTestCase;

final class SettingsTest extends FeatureTestCase
{
    private int $adminId;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(SettingsCatalog::class)->add(
            SettingDefinition::integer('probe.limit', 5, 1, 50, 'How many things are allowed.', overridable: true),
            SettingDefinition::integer('probe.global_only', 10, 1, 100, 'Cannot be overridden per company.'),
            SettingDefinition::boolean('probe.enabled', true, 'A switch.'),
            SettingDefinition::text('probe.label', 'Hello', 20, 'A label.'),
            SettingDefinition::choices('probe.templates', ['classic'], ['classic', 'modern', 'compact'], 'Active templates.', minItems: 1),
        );

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo('settings.manage');
        $this->adminId = $admin->id;
        $this->token = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    private function settings(): Settings
    {
        return $this->app->make(Settings::class);
    }

    private function asAdmin(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, "/api/v1/admin/{$uri}", $data);
    }

    /** @return list<object> */
    private function audit(string $action): array
    {
        return DB::table('platform_audit_logs')->where('action', $action)->orderBy('id')->get()->all();
    }

    public function test_a_setting_without_a_stored_value_has_its_default(): void
    {
        $this->assertSame(5, $this->settings()->integer('probe.limit'));
        $this->assertTrue($this->settings()->boolean('probe.enabled'));
        $this->assertSame('Hello', $this->settings()->text('probe.label'));
        $this->assertSame(['classic'], $this->settings()->choices('probe.templates'));
        $this->assertSame(0, DB::table('platform_settings')->count());
    }

    public function test_the_global_value_an_admin_set_wins_over_the_default(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('probe.limit', 12, $this->adminId);

        $this->assertSame(12, $this->settings()->integer('probe.limit'));
        $this->assertSame(12, $this->settings()->integer('probe.limit', 99));
    }

    public function test_a_company_override_wins_for_that_company_only_and_only_when_allowed(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal('probe.limit', 12, null);
        $store->setCompanyOverride('probe.limit', 7, 30, $this->adminId);
        $store->setCompanyOverride('probe.global_only', 7, 77, $this->adminId);

        $this->assertSame(30, $this->settings()->integer('probe.limit', 7));
        $this->assertSame(12, $this->settings()->integer('probe.limit', 8));
        $this->assertSame(12, $this->settings()->integer('probe.limit'));
        $this->assertSame(10, $this->settings()->integer('probe.global_only', 7));
    }

    public function test_removing_an_override_or_the_global_value_falls_back(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal('probe.limit', 12, null);
        $store->setCompanyOverride('probe.limit', 7, 30, null);

        $this->assertTrue($store->removeCompanyOverride('probe.limit', 7));
        $this->assertFalse($store->removeCompanyOverride('probe.limit', 7));
        $this->assertSame(12, $this->settings()->integer('probe.limit', 7));

        $this->assertTrue($store->resetGlobal('probe.limit'));
        $this->assertFalse($store->resetGlobal('probe.limit'));
        $this->assertSame(5, $this->settings()->integer('probe.limit', 7));
    }

    public function test_a_stored_value_the_definition_no_longer_accepts_is_ignored(): void
    {
        DB::table('platform_settings')->insert(['key' => 'probe.limit', 'value' => json_encode(500), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('platform_setting_overrides')->insert(['key' => 'probe.limit', 'scope_type' => 'company', 'scope_id' => 7, 'value' => json_encode('lots'), 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(5, $this->settings()->integer('probe.limit', 7));
    }

    public function test_a_wrong_or_unknown_key_is_a_programming_error(): void
    {
        try {
            $this->settings()->integer('probe.enabled');
            $this->fail('A boolean read as an integer must throw.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->settings()->integer('probe.unknown');
    }

    public function test_values_are_cached_and_a_write_through_the_store_makes_the_change_visible(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal('probe.limit', 12, null);
        $this->assertSame(12, $this->settings()->integer('probe.limit'));
        $this->assertTrue(Cache::has('platform:settings'));

        // A change made behind the store's back is not seen: the cache is what readers use.
        DB::table('platform_settings')->where('key', 'probe.limit')->update(['value' => json_encode(40)]);
        $this->app->forgetInstance(SettingsStore::class);
        $this->assertSame(12, $this->app->make(Settings::class)->integer('probe.limit'));

        $this->app->make(SettingsStore::class)->setGlobal('probe.limit', 41, null);
        $this->app->forgetInstance(SettingsStore::class);
        $this->assertSame(41, $this->app->make(Settings::class)->integer('probe.limit'));
    }

    public function test_a_company_override_is_cached_too_and_invalidated_by_a_write(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setCompanyOverride('probe.limit', 7, 30, null);
        $this->assertSame(30, $this->settings()->integer('probe.limit', 7));

        $store->setCompanyOverride('probe.limit', 7, 31, null);

        $this->assertSame(31, $this->settings()->integer('probe.limit', 7));
    }

    public function test_the_admin_api_needs_the_settings_permission(): void
    {
        $this->getJson('/api/v1/admin/settings')->assertStatus(401);

        $other = AdminModel::query()->create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'hash', 'status' => 'active']);
        $other->givePermissionTo('faqs.manage');
        $token = $other->createToken('console', ['*'], now()->addHour())->plainTextToken;

        $this->asAdmin('GET', 'settings', token: $token)->assertStatus(403);
        $this->asAdmin('PUT', 'settings/probe.limit', ['value' => 9], $token)->assertStatus(403);
        $this->asAdmin('DELETE', 'settings/probe.limit', token: $token)->assertStatus(403);
    }

    public function test_the_list_shows_every_setting_with_its_type_constraints_and_default(): void
    {
        $response = $this->asAdmin('GET', 'settings')->assertOk();

        $keys = array_column($response->json('data'), 'key');
        $this->assertSame([
            'analytics.min_group_size',
            'assessment.band_excellent_from', 'assessment.band_fair_from', 'assessment.band_good_from', 'assessment.max_company_tests', 'assessment.max_drawing_megabytes', 'assessment.max_due_days', 'assessment.max_questions_per_section', 'assessment.max_section_minutes', 'assessment.max_sections_per_test', 'assessment.min_due_hours', 'assessment.psychological_enabled',
            'company.banner_max_size_kb', 'company.document_max_size_kb', 'company.logo_max_size_kb',
            'identity.avatar_max_size_kb',
            'invitations.max_per_day',
            'job_seeker.available_from_max_days', 'job_seeker.candidate_sensitive_filters_enabled', 'job_seeker.candidate_views_per_day', 'job_seeker.cv_generations_per_hour', 'job_seeker.max_age', 'job_seeker.min_age', 'job_seeker.resume_max_size_kb',
            'jobalert.max_alerts_per_user',
            'jobs.max_deadline_days', 'jobs.require_approval',
            'messaging.max_attachment_bytes', 'messaging.max_bulk_recipients', 'messaging.max_daily_company_messages',
            'probe.enabled', 'probe.global_only', 'probe.label', 'probe.limit', 'probe.templates',
            'recruitment.max_applications_per_day', 'recruitment.max_bulk_applications', 'recruitment.max_export_rows', 'recruitment.max_screening_questions', 'recruitment.max_stages', 'recruitment.screening_enabled',
        ], $keys);

        $limit = collect($response->json('data'))->firstWhere('key', 'probe.limit');
        $this->assertSame('integer', $limit['type']);
        $this->assertSame(5, $limit['default']);
        $this->assertSame(5, $limit['value']);
        $this->assertTrue($limit['is_default']);
        $this->assertTrue($limit['overridable_per_company']);
        $this->assertSame(['min' => 1, 'max' => 50], $limit['constraints']);
        $this->assertNull($limit['updated_at']);

        $templates = collect($response->json('data'))->firstWhere('key', 'probe.templates');
        $this->assertSame(['allowed_values' => ['classic', 'modern', 'compact'], 'min_items' => 1], $templates['constraints']);
        $this->assertSame([], collect($response->json('data'))->firstWhere('key', 'probe.enabled')['constraints']);
        $this->assertFalse(collect($response->json('data'))->firstWhere('key', 'probe.global_only')['overridable_per_company']);
    }

    public function test_an_update_stores_the_value_audits_it_and_reports_who_changed_it(): void
    {
        $this->asAdmin('PUT', 'settings/probe.limit', ['value' => 12])
            ->assertOk()
            ->assertJsonPath('data.value', 12)
            ->assertJsonPath('data.default', 5)
            ->assertJsonPath('data.is_default', false)
            ->assertJsonPath('data.updated_by_admin_id', $this->adminId);

        $this->assertSame(12, $this->settings()->integer('probe.limit'));

        $entries = $this->audit('settings.updated');
        $this->assertCount(1, $entries);
        $this->assertSame('admin', $entries[0]->actor_type);
        $this->assertSame($this->adminId, (int) $entries[0]->actor_id);
        $this->assertSame('setting', $entries[0]->subject_type);
        $this->assertSame(['key' => 'probe.limit', 'old' => 5, 'new' => 12], json_decode($entries[0]->metadata, true));
        $this->assertSame('127.0.0.1', $entries[0]->ip_address);
    }

    public function test_every_type_can_be_updated_and_lists_are_audited_as_lists(): void
    {
        $this->asAdmin('PUT', 'settings/probe.enabled', ['value' => false])->assertOk()->assertJsonPath('data.value', false);
        $this->asAdmin('PUT', 'settings/probe.label', ['value' => '  Selamat '])->assertOk()->assertJsonPath('data.value', 'Selamat');
        $this->asAdmin('PUT', 'settings/probe.templates', ['value' => ['modern', 'classic', 'modern']])->assertOk()->assertJsonPath('data.value', ['modern', 'classic']);

        $this->assertFalse($this->settings()->boolean('probe.enabled'));
        $this->assertSame('Selamat', $this->settings()->text('probe.label'));
        $this->assertSame(['modern', 'classic'], $this->settings()->choices('probe.templates'));

        $last = $this->audit('settings.updated')[2];
        $this->assertSame(['key' => 'probe.templates', 'old' => ['classic'], 'new' => ['modern', 'classic']], json_decode($last->metadata, true));
    }

    public function test_setting_the_value_already_in_force_changes_and_audits_nothing(): void
    {
        $this->asAdmin('PUT', 'settings/probe.limit', ['value' => 12])->assertOk();
        $before = DB::table('platform_settings')->where('key', 'probe.limit')->value('updated_at');

        $this->asAdmin('PUT', 'settings/probe.limit', ['value' => 12])->assertOk()->assertJsonPath('data.value', 12);

        $this->assertCount(1, $this->audit('settings.updated'));
        $this->assertSame($before, DB::table('platform_settings')->where('key', 'probe.limit')->value('updated_at'));
        $this->assertSame(1, DB::table('platform_settings')->count());
    }

    public function test_invalid_values_are_refused_with_422_and_nothing_is_stored_or_audited(): void
    {
        foreach ([
            ['probe.limit', 0], ['probe.limit', 51], ['probe.limit', '5'], ['probe.enabled', 'yes'], ['probe.label', str_repeat('x', 21)],
            ['probe.templates', []], ['probe.templates', ['classic', 'fancy']], ['probe.templates', 'classic'], ['probe.limit', null],
        ] as [$key, $value]) {
            $this->asAdmin('PUT', "settings/{$key}", ['value' => $value])
                ->assertStatus(422)
                ->assertJsonPath('type', 'urn:jobportal:error:invalid-setting-value');
        }

        $this->asAdmin('PUT', 'settings/probe.limit', [])->assertStatus(422)->assertJsonValidationErrors(['value'], 'errors');

        $this->assertSame(0, DB::table('platform_settings')->count());
        $this->assertSame([], $this->audit('settings.updated'));
    }

    public function test_an_unknown_setting_is_a_404(): void
    {
        $this->asAdmin('PUT', 'settings/probe.unknown', ['value' => 1])->assertStatus(404)->assertJsonPath('type', 'urn:jobportal:error:not-found');
        $this->asAdmin('DELETE', 'settings/probe.unknown')->assertStatus(404);
    }

    public function test_a_reset_returns_to_the_default_and_is_audited(): void
    {
        $this->asAdmin('PUT', 'settings/probe.limit', ['value' => 12])->assertOk();

        $this->asAdmin('DELETE', 'settings/probe.limit')
            ->assertOk()
            ->assertJsonPath('data.value', 5)
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.updated_at', null);

        $this->assertSame(5, $this->settings()->integer('probe.limit'));
        $this->assertSame(0, DB::table('platform_settings')->count());

        $entries = $this->audit('settings.reset');
        $this->assertCount(1, $entries);
        $this->assertSame(['key' => 'probe.limit', 'old' => 12], json_decode($entries[0]->metadata, true));
    }

    public function test_resetting_a_setting_that_is_at_its_default_audits_nothing(): void
    {
        $this->asAdmin('DELETE', 'settings/probe.limit')->assertOk()->assertJsonPath('data.is_default', true);

        $this->assertSame([], $this->audit('settings.reset'));
    }

    public function test_the_admin_value_is_the_global_one_and_company_overrides_stay_out_of_the_list(): void
    {
        $this->app->make(SettingsStore::class)->setCompanyOverride('probe.limit', 7, 30, null);

        $this->asAdmin('GET', 'settings')->assertOk();
        $limit = collect($this->asAdmin('GET', 'settings')->json('data'))->firstWhere('key', 'probe.limit');

        $this->assertSame(5, $limit['value']);
        $this->assertTrue($limit['is_default']);
        $this->assertSame(30, $this->settings()->integer('probe.limit', 7));
    }
}
