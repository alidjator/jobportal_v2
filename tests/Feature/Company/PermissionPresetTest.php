<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** The permission presets a company admin saves (`docs/design/phase-8-company-permissions.md`, changed 2026-10-30). */
final class PermissionPresetTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private int $companyId;

    private string $adminToken;

    private string $recruiterToken;

    private string $otherAdminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = $this->company('PT Maju Bersama', 'pt-maju-bersama');
        $this->adminToken = $this->token($this->companyId, 'andi@example.test', 'company_admin');
        $this->recruiterToken = $this->token($this->companyId, 'rina@example.test', 'recruiter');
        $this->otherAdminToken = $this->token($this->company('PT Lain', 'pt-lain'), 'lain@example.test', 'company_admin');
    }

    private function company(string $name, string $slug): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => $name, 'slug' => $slug, 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function token(int $companyId, string $email, string $role): string
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $email, 'email' => $email, 'password' => Hash::make('password-123'),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->json($method, '/api/v1/company'.$uri, $body);
    }

    /** @param  list<string>  $permissions */
    private function create(string $name = 'Recruiter tanpa ekspor', array $permissions = ['applications.view', 'applications.review', 'applications.manage'], ?string $token = null): TestResponse
    {
        return $this->api('POST', '/permission-presets', ['name' => $name, 'permissions' => $permissions], $token);
    }

    public function test_only_a_company_admin_manages_presets(): void
    {
        $this->create(token: $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-admin-required');
        $this->api('GET', '/permission-presets', token: $this->recruiterToken)->assertForbidden();
        $id = (int) $this->create()->assertCreated()->json('data.id');
        $this->api('PATCH', "/permission-presets/{$id}", ['name' => 'X1', 'permissions' => ['applications.view']], $this->recruiterToken)->assertForbidden();
        $this->api('DELETE', "/permission-presets/{$id}", token: $this->recruiterToken)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/company/permission-presets')->assertUnauthorized();
    }

    public function test_a_preset_is_saved_listed_changed_and_deleted_with_an_audit_entry_each(): void
    {
        $response = $this->create('  Tim wawancara  ', ['applications.view', 'interviews.participate'])->assertCreated();
        $response->assertJsonPath('data.name', 'Tim wawancara')->assertJsonPath('data.permissions', ['applications.view', 'interviews.participate']);
        $id = (int) $response->json('data.id');

        $this->api('GET', '/permission-presets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);

        $this->api('PATCH', "/permission-presets/{$id}", ['name' => 'Tim interview', 'permissions' => ['applications.view']])->assertOk()
            ->assertJsonPath('data.name', 'Tim interview')->assertJsonPath('data.permissions', ['applications.view']);

        $this->api('DELETE', "/permission-presets/{$id}")->assertNoContent();
        $this->api('GET', '/permission-presets')->assertOk()->assertJsonCount(0, 'data');
        $this->api('DELETE', "/permission-presets/{$id}")->assertNotFound();

        foreach (['created', 'updated', 'deleted'] as $event) {
            $this->assertSame(1, DB::table('platform_audit_logs')->where('action', "company.permission_preset_{$event}")->count());
        }
    }

    public function test_the_permissions_follow_the_rules_of_a_member(): void
    {
        $this->create(permissions: [])->assertStatus(422);
        $this->create(permissions: ['not.a.permission'])->assertStatus(422);
        $this->create(permissions: ['applications.manage'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:permission-requires-view');
        $this->create(permissions: ['applications.view', 'applications.manage'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:permission-requires-review');
        $this->create('A')->assertStatus(422);
        $this->create(str_repeat('a', 61))->assertStatus(422);
    }

    public function test_a_name_is_taken_once_per_company_whatever_the_case_and_a_company_holds_twenty(): void
    {
        $this->create('Tim A')->assertCreated();
        $this->create('tim a')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:permission-preset-name-taken');
        $this->create('Tim A', token: $this->otherAdminToken)->assertCreated();

        for ($i = 2; $i <= 20; $i++) {
            $this->create("Tim {$i}")->assertCreated();
        }

        $this->create('Tim 21')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:permission-preset-limit');

        $second = (int) $this->api('GET', '/permission-presets')->json('data.1.id');
        $this->api('PATCH', "/permission-presets/{$second}", ['name' => 'TIM A', 'permissions' => ['applications.view']])->assertStatus(409);
    }

    public function test_a_company_sees_only_its_own_presets(): void
    {
        $id = (int) $this->create()->assertCreated()->json('data.id');

        $this->api('GET', '/permission-presets', token: $this->otherAdminToken)->assertOk()->assertJsonCount(0, 'data');
        $this->api('PATCH', "/permission-presets/{$id}", ['name' => 'Curian', 'permissions' => ['applications.view']], $this->otherAdminToken)->assertNotFound();
        $this->api('DELETE', "/permission-presets/{$id}", token: $this->otherAdminToken)->assertNotFound();
        $this->assertSame(1, DB::table('company_permission_presets')->count());
    }

    public function test_the_catalog_lists_the_saved_presets_for_an_admin_only(): void
    {
        $id = (int) $this->create('Tim wawancara', ['applications.view', 'interviews.participate'])->assertCreated()->json('data.id');

        $presets = $this->api('GET', '/permissions')->assertOk()->json('data.presets');
        $saved = collect($presets)->firstWhere('id', $id);
        $this->assertSame(['preset' => "saved_{$id}", 'role' => 'member', 'permissions' => ['applications.view', 'interviews.participate'], 'id' => $id, 'name' => 'Tim wawancara'], $saved);
        $this->assertSame(4, collect($presets)->whereNull('id')->count());

        $this->assertSame(4, count($this->api('GET', '/permissions', token: $this->recruiterToken)->assertOk()->json('data.presets')));
        $this->assertSame([], collect($this->api('GET', '/permissions', token: $this->otherAdminToken)->json('data.presets'))->whereNotNull('id')->all());
    }

    public function test_a_preset_is_a_copy_so_deleting_it_changes_no_member(): void
    {
        $id = (int) $this->create('Tim wawancara', ['applications.view', 'interviews.participate'])->assertCreated()->json('data.id');
        $member = UserModel::query()->where('email', 'rina@example.test')->firstOrFail();

        $this->api('PATCH', "/members/{$member->id}", ['permissions' => ['applications.view', 'interviews.participate']])->assertNoContent();
        $this->api('DELETE', "/permission-presets/{$id}")->assertNoContent();

        $held = DB::table('company_member_permissions')->join('company_members', 'company_members.id', '=', 'company_member_permissions.company_member_id')->where('company_members.user_id', $member->id)->pluck('permission')->all();
        $this->assertEqualsCanonicalizing(['applications.view', 'interviews.participate'], $held);
    }
}
