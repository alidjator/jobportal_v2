<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/phase-8-company-admin.md` section 4: the settings page of a company. */
final class CompanySettingsTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string KEY = 'company.require_two_factor';

    private int $companyId;

    private UserModel $owner;

    private string $ownerToken;

    private string $recruiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');

        $this->companyId = (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Maju Bersama', 'slug' => 'pt-maju-bersama', 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->owner = $this->member('Andi Admin', 'andi@example.test', 'company_admin');
        $this->ownerToken = $this->tokenOf($this->owner);
        $this->recruiterToken = $this->tokenOf($this->member('Rina Rekruter', 'rina@example.test', 'recruiter'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(string $name, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $name, 'email' => $email, 'password' => Hash::make('password-123'),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($this->companyId, $user->id, $role);

        return $user;
    }

    private function tokenOf(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->ownerToken)->json($method, '/api/v1/company/settings', $body);
    }

    private ?string $platformAdminToken = null;

    private function platformAdminToken(): string
    {
        if ($this->platformAdminToken !== null) {
            return $this->platformAdminToken;
        }

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['settings.manage']);

        return $this->platformAdminToken = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    private function adminApi(string $method, string $suffix = ''): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->platformAdminToken())->json($method, "/api/v1/admin/companies/{$this->companyId}/settings{$suffix}");
    }

    public function test_any_member_reads_the_settings_with_the_default_and_the_range(): void
    {
        foreach ([$this->ownerToken, $this->recruiterToken] as $token) {
            $response = $this->api('GET', [], $token)->assertOk();

            $setting = collect($response->json('data'))->firstWhere('key', self::KEY);
            $this->assertSame(
                ['key' => self::KEY, 'type' => 'boolean', 'description' => $setting['description'], 'value' => false, 'default' => false, 'min' => null, 'max' => null, 'allowed_values' => null],
                $setting,
            );
        }
    }

    public function test_the_page_holds_only_what_a_company_may_change(): void
    {
        $keys = array_column($this->api('GET')->assertOk()->json('data'), 'key');

        $this->assertSame([self::KEY], $keys);
        $this->assertNotContains('company.document_max_size_kb', $keys, 'overridable by the platform, not editable by the company');
    }

    public function test_only_a_member_reads_and_only_a_company_admin_changes(): void
    {
        $this->api('PATCH', [self::KEY => true], $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-admin-required');
        $this->assertSame(0, DB::table('platform_setting_overrides')->count());

        $seeker = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'id']);
        $this->api('GET', [], $this->tokenOf($seeker))->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/company/settings')->assertUnauthorized();
        $this->flushHeaders()->patchJson('/api/v1/company/settings', [self::KEY => true])->assertUnauthorized();
    }

    public function test_a_company_admin_saves_the_value_as_the_company_override_that_a_platform_admin_sees_and_resets(): void
    {
        $response = $this->api('PATCH', [self::KEY => true])->assertOk();
        $this->assertTrue(collect($response->json('data'))->firstWhere('key', self::KEY)['value']);
        $this->assertTrue(collect($this->api('GET', [], $this->tokenOf(UserModel::query()->where('email', 'rina@example.test')->firstOrFail()))->json('data'))->firstWhere('key', self::KEY)['value']);

        $override = DB::table('platform_setting_overrides')->where('key', self::KEY)->first();
        $this->assertSame('company', $override->scope_type);
        $this->assertSame($this->companyId, (int) $override->scope_id);

        $seen = collect($this->adminApi('GET')->assertOk()->json('data'))->firstWhere('key', self::KEY);
        $this->assertTrue($seen['has_override']);
        $this->assertTrue($seen['value']);

        $this->adminApi('DELETE', '/'.self::KEY)->assertOk();
        $this->assertFalse(collect($this->api('GET')->json('data'))->firstWhere('key', self::KEY)['value']);
    }

    public function test_the_change_is_audited_with_the_values_before_and_after_and_a_repeat_is_not(): void
    {
        $this->api('PATCH', [self::KEY => true])->assertOk();
        $this->api('PATCH', [self::KEY => true])->assertOk();

        $entries = DB::table('platform_audit_logs')->where('action', 'company.settings_updated')->get();
        $this->assertCount(1, $entries);
        $this->assertSame($this->owner->id, (int) $entries[0]->actor_id);
        $this->assertSame('user', $entries[0]->actor_type);
        $this->assertSame($this->companyId, (int) $entries[0]->company_id);
        $this->assertSame(['before' => [self::KEY => false], 'after' => [self::KEY => true]], json_decode((string) $entries[0]->metadata, true));

        $this->api('PATCH', [self::KEY => false])->assertOk();
        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'company.settings_updated')->count());
    }

    public function test_a_key_the_company_may_not_change_is_refused(): void
    {
        $this->api('PATCH', ['no.such_setting' => 1])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:setting-not-editable');
        $this->api('PATCH', ['company.document_max_size_kb' => 100])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:setting-not-editable');
        $this->api('PATCH', ['identity.avatar_max_size_kb' => 100])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:setting-not-editable');

        $this->assertSame(0, DB::table('platform_setting_overrides')->count());
    }

    public function test_a_value_the_setting_refuses_is_named_by_key_and_nothing_is_saved(): void
    {
        $this->api('PATCH', [self::KEY => 'yes'])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-setting-value')
            ->assertJson(['errors' => [self::KEY => 'The value of company.require_two_factor must be true or false.']]);

        $this->assertSame(0, DB::table('platform_setting_overrides')->count());
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'company.settings_updated')->count());
    }

    public function test_a_suspended_company_changes_nothing(): void
    {
        DB::table('company_companies')->update(['status' => 'suspended']);

        $this->api('PATCH', [self::KEY => true])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-suspended');
        $this->api('GET')->assertOk();
    }
}
