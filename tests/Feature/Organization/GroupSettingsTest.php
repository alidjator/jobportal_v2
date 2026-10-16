<?php

namespace Tests\Feature\Organization;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Settings\SettingDefinition;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/erp-governance.md` section 3: the `group` scope of the settings (company, then group, then global, then default) and `group.settings.manage`. */
final class GroupSettingsTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private ?string $adminToken = null;

    private int $group;

    private int $holding;

    private int $holdingAdmin;

    private int $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');

        // The group settings of the ERP are registered when their module is coded; these stand in for them.
        $this->app->make(SettingsCatalog::class)->add(
            SettingDefinition::integer('test.group_limit', 10, 1, 100, 'A limit a group sets.', groupEditable: true),
            SettingDefinition::integer('test.both_limit', 5, 1, 100, 'A limit a group or a company sets.', overridable: true, groupEditable: true),
            SettingDefinition::integer('test.company_limit', 3, 1, 100, 'A limit only a company sets.', overridable: true),
        );

        $this->holding = $this->companyRowId('pt-holding');
        $this->holdingAdmin = $this->member($this->holding, 'hana@example.test', 'company_admin');
        $this->viewer = $this->member($this->holding, 'vera@example.test', 'recruiter');

        $created = $this->admin('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding Nusantara', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $this->admin('PUT', "groups/{$this->group}/legal-entities/{$created->json('data.legal_entities.0.id')}/tenant", ['company_id' => $this->holding])->assertOk();
        $this->admin('PUT', "groups/{$this->group}/members/{$this->holdingAdmin}", ['permissions' => ['group.settings.manage']])->assertOk();
        $this->admin('PUT', "groups/{$this->group}/members/{$this->viewer}", ['permissions' => ['group.recruitment.view']])->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(int $companyId, string $email, string $role): int
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $email, 'email' => $email, 'password' => Hash::make('password-123'), 'status' => 'active', 'locale' => 'en',
            'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return (int) $user->id;
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body = []): TestResponse
    {
        if ($this->adminToken === null) {
            $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
            Artisan::call('identity:sync-permissions');
            $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
            $admin->givePermissionTo(['companies.manage']);
            $this->adminToken = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
        }

        $this->app['auth']->forgetGuards();

        return $this->withToken($this->adminToken)->json($method, "/api/v1/admin/{$uri}", $body);
    }

    /** @param  array<string, mixed>  $body */
    private function asMember(int $userId, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $token = UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        return $this->withToken($token)->json($method, "/api/v1/organization/{$uri}", $body);
    }

    private function limit(string $key, ?int $companyId = null): int
    {
        return $this->app->make(Settings::class)->integer($key, $companyId);
    }

    public function test_any_group_member_reads_only_the_settings_a_group_may_set(): void
    {
        $page = $this->asMember($this->viewer, 'GET', 'settings')->assertOk();

        $this->assertSame(['test.both_limit', 'test.group_limit'], array_column($page->json('data'), 'key'));
        $page->assertJsonPath('data.1.value', 10)->assertJsonPath('data.1.default', 10)->assertJsonPath('data.1.type', 'integer')->assertJsonPath('data.1.min', 1)->assertJsonPath('data.1.max', 100);

        $stranger = $this->member($this->companyRowId('pt-luar'), 'luar@example.test', 'company_admin');
        $this->asMember($stranger, 'GET', 'settings')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-group-member');
    }

    public function test_a_group_admin_sets_a_value_that_applies_to_the_tenants_of_the_group_and_audits_it(): void
    {
        $outsider = $this->companyRowId('pt-luar');

        $this->asMember($this->holdingAdmin, 'PATCH', 'settings', ['test.group_limit' => 25])->assertOk()->assertJsonPath('data.1.value', 25)->assertJsonPath('data.1.default', 10);

        $this->assertSame(25, $this->limit('test.group_limit', $this->holding), 'a tenant of the group');
        $this->assertSame(10, $this->limit('test.group_limit', $outsider), 'an external tenant');
        $this->assertSame(10, $this->limit('test.group_limit'), 'no tenant');

        $entry = DB::table('platform_audit_logs')->where('action', 'group.settings_updated')->first();
        $this->assertSame($this->group, (int) $entry->group_id);
        $this->assertSame($this->holdingAdmin, (int) $entry->actor_id);
        $metadata = json_decode((string) $entry->metadata, true);
        $this->assertSame(['test.group_limit' => 10], $metadata['before']);
        $this->assertSame(['test.group_limit' => 25], $metadata['after']);

        $this->asMember($this->holdingAdmin, 'PATCH', 'settings', ['test.group_limit' => 25])->assertOk();
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'group.settings_updated')->count(), 'an unchanged value audits nothing');
    }

    public function test_the_order_is_company_then_group_then_global_then_default(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $this->assertSame(5, $this->limit('test.both_limit', $this->holding), 'default');

        $store->setGlobal('test.both_limit', 8, null);
        $this->assertSame(8, $this->limit('test.both_limit', $this->holding), 'global');

        $this->asMember($this->holdingAdmin, 'PATCH', 'settings', ['test.both_limit' => 20])->assertOk();
        $this->assertSame(20, $this->limit('test.both_limit', $this->holding), 'group');
        $this->assertSame(8, $this->limit('test.both_limit'), 'global still applies without a tenant');

        $store->setCompanyOverride('test.both_limit', $this->holding, 30, null);
        $this->assertSame(30, $this->limit('test.both_limit', $this->holding), 'company');
    }

    public function test_a_setting_a_group_may_not_set_is_refused_and_a_bad_value_saves_nothing(): void
    {
        $this->asMember($this->holdingAdmin, 'PATCH', 'settings', ['test.company_limit' => 4])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:setting-not-editable');
        $this->asMember($this->holdingAdmin, 'PATCH', 'settings', ['no.such_setting' => 1])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:setting-not-editable');

        $this->asMember($this->holdingAdmin, 'PATCH', 'settings', ['test.group_limit' => 25, 'test.both_limit' => 1000])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-setting-value')->assertJsonStructure(['errors' => ['test.both_limit']]);
        $this->assertSame(10, $this->limit('test.group_limit', $this->holding), 'all or none');
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'group.settings_updated')->count());
    }

    public function test_changing_the_settings_needs_group_settings_manage(): void
    {
        $this->asMember($this->viewer, 'PATCH', 'settings', ['test.group_limit' => 25])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:group-permission-required');
        $this->assertSame(10, $this->limit('test.group_limit', $this->holding));
    }

    public function test_the_values_of_one_group_do_not_reach_another(): void
    {
        $this->asMember($this->holdingAdmin, 'PATCH', 'settings', ['test.group_limit' => 25])->assertOk();

        $other = $this->admin('POST', 'groups', ['name' => 'Grup Lain', 'legal_entity' => ['legal_name' => 'PT Lain', 'legal_form' => 'pt']])->assertCreated();
        $company = $this->companyRowId('pt-lain');
        $user = $this->member($company, 'lain@example.test', 'company_admin');
        $this->admin('PUT', "groups/{$other->json('data.id')}/legal-entities/{$other->json('data.legal_entities.0.id')}/tenant", ['company_id' => $company])->assertOk();
        $this->admin('PUT', "groups/{$other->json('data.id')}/members/{$user}", ['permissions' => ['group.settings.manage']])->assertOk();

        $this->assertSame(10, $this->limit('test.group_limit', $company));
        $this->asMember($user, 'GET', 'settings')->assertOk()->assertJsonPath('data.1.value', 10);
    }
}
