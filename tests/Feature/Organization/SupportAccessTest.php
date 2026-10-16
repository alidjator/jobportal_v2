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
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/erp-governance.md` section 1.1: temporary, revocable, read-only support access of a platform admin to a group, every read audited. */
final class SupportAccessTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private int $group;

    private int $entity;

    private int $holdingAdmin;

    private int $viewer;

    private AdminModel $support;

    private AdminModel $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->support = AdminModel::query()->create(['name' => 'Sukma Support', 'email' => 'support@example.test', 'password' => 'hash', 'status' => 'active']);
        $this->support->givePermissionTo(['groups.support']);
        $this->operator = AdminModel::query()->create(['name' => 'Oki Operator', 'email' => 'oki@example.test', 'password' => 'hash', 'status' => 'active']);
        $this->operator->givePermissionTo(['companies.manage']);

        $holding = $this->companyRowId('pt-holding');
        $this->holdingAdmin = $this->member($holding, 'hana@example.test', 'company_admin');
        $this->viewer = $this->member($holding, 'vera@example.test', 'recruiter');

        $created = $this->asAdmin($this->operator, 'POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding Nusantara', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $this->entity = (int) $created->json('data.legal_entities.0.id');
        $this->asAdmin($this->operator, 'PUT', "groups/{$this->group}/legal-entities/{$this->entity}/tenant", ['company_id' => $holding])->assertOk();
        $this->asAdmin($this->operator, 'PUT', "groups/{$this->group}/members/{$this->holdingAdmin}", ['permissions' => ['group.structure.manage', 'group.members.manage', 'group.audit.view']])->assertOk();
        $this->asAdmin($this->operator, 'PUT', "groups/{$this->group}/members/{$this->viewer}", ['permissions' => ['group.recruitment.view']])->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(int $companyId, string $email, string $role): int
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('password-123'), 'status' => 'active', 'locale' => 'en',
            'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return (int) $user->id;
    }

    /** @param  array<string, mixed>  $body */
    private function asAdmin(AdminModel $admin, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($admin->createToken('console', ['*'], now()->addDay())->plainTextToken)->json($method, "/api/v1/admin/{$uri}", $body);
    }

    /** @param  array<string, mixed>  $body */
    private function asMember(int $userId, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $token = UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        return $this->withToken($token)->json($method, "/api/v1/organization/{$uri}", $body);
    }

    private function grant(int $hours = 24): int
    {
        return (int) $this->asMember($this->holdingAdmin, 'POST', 'support-grants', ['admin_id' => $this->support->id, 'hours' => $hours])->assertCreated()->json('data.id');
    }

    private function read(string $path): TestResponse
    {
        return $this->asAdmin($this->support, 'GET', "support/{$path}".(str_contains($path, '?') ? '&' : '?')."group_id={$this->group}");
    }

    public function test_a_group_admin_sees_who_can_receive_support_access_and_only_a_members_manager_does(): void
    {
        $this->asMember($this->holdingAdmin, 'GET', 'support-admins')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->support->id)->assertJsonPath('data.0.name', 'Sukma Support');

        $this->asMember($this->viewer, 'GET', 'support-admins')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:group-permission-required');
        $this->asMember($this->viewer, 'POST', 'support-grants', ['admin_id' => $this->support->id, 'hours' => 1])->assertForbidden();
    }

    public function test_a_grant_is_for_one_to_seventy_two_hours_to_an_admin_who_may_receive_it_and_is_audited(): void
    {
        $response = $this->asMember($this->holdingAdmin, 'POST', 'support-grants', ['admin_id' => $this->support->id, 'hours' => 24])->assertCreated();
        $response->assertJsonPath('data.status', 'active')->assertJsonPath('data.admin.name', 'Sukma Support')->assertJsonPath('data.granted_by.name', 'Hana')
            ->assertJsonPath('data.expires_at', '2026-10-17T09:00:00+00:00');

        $entry = DB::table('platform_audit_logs')->where('action', 'group.support_granted')->first();
        $this->assertSame($this->group, (int) $entry->group_id);
        $this->assertSame($this->support->id, json_decode((string) $entry->metadata, true)['admin_id']);

        $this->asMember($this->holdingAdmin, 'POST', 'support-grants', ['admin_id' => $this->support->id, 'hours' => 5])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:support-grant-exists');
        $this->asMember($this->holdingAdmin, 'POST', 'support-grants', ['admin_id' => $this->operator->id, 'hours' => 5])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-support-admin');
        $this->asMember($this->holdingAdmin, 'POST', 'support-grants', ['admin_id' => $this->support->id, 'hours' => 73])->assertStatus(422)->assertJsonValidationErrors(['hours']);
        $this->asMember($this->holdingAdmin, 'POST', 'support-grants', ['admin_id' => $this->support->id, 'hours' => 0])->assertStatus(422)->assertJsonValidationErrors(['hours']);

        $this->asMember($this->holdingAdmin, 'GET', 'support-grants')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_without_a_grant_a_platform_admin_reads_nothing_of_the_group(): void
    {
        $this->asAdmin($this->support, 'GET', 'support/groups')->assertOk()->assertJsonCount(0, 'data');
        $this->read('units?legal_entity_id='.$this->entity)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:support-access-required');
        $this->read('group')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:support-access-required');
        $this->asAdmin($this->support, 'GET', 'support/units')->assertStatus(422)->assertJsonValidationErrors(['group_id']);

        $this->asAdmin($this->operator, 'GET', "support/units?group_id={$this->group}")->assertForbidden();
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'group.support_read')->count(), 'a refused read is not a read');
    }

    public function test_under_a_grant_the_structure_and_the_log_can_be_read_and_every_read_is_audited_and_shown_to_the_group(): void
    {
        $type = (int) $this->asMember($this->holdingAdmin, 'POST', 'unit-types', ['code' => 'DEPT', 'name' => 'Department', 'level_order' => 2])->assertCreated()->json('data.id');
        $unit = (int) $this->asMember($this->holdingAdmin, 'POST', 'units', ['legal_entity_id' => $this->entity, 'code' => 'FIN', 'name' => 'Finance', 'unit_type_id' => $type])->assertCreated()->json('data.id');
        $this->grant();

        $this->asAdmin($this->support, 'GET', 'support/groups')->assertOk()->assertJsonPath('data.0.group_id', $this->group)->assertJsonPath('data.0.name', 'Grup Nusantara')
            ->assertJsonPath('data.0.expires_at', '2026-10-17T09:00:00+00:00');
        $this->read('group')->assertOk()->assertJsonPath('data.name', 'Grup Nusantara')->assertJsonPath('data.my_permissions', []);
        $this->read("units?legal_entity_id={$this->entity}")->assertOk()->assertJsonPath('data.0.name', 'Finance');
        $this->read("units/{$unit}/versions")->assertOk()->assertJsonCount(1, 'data');
        $this->read('unit-types')->assertOk()->assertJsonPath('data.0.code', 'DEPT');
        $this->read('grades')->assertOk();
        $this->read("positions?legal_entity_id={$this->entity}")->assertOk();
        $this->read("cost-centres?legal_entity_id={$this->entity}")->assertOk();
        $this->read('activity')->assertOk();

        $this->assertSame(8, DB::table('platform_audit_logs')->where('action', 'group.support_read')->count());
        $first = DB::table('platform_audit_logs')->where('action', 'group.support_read')->orderBy('id')->first();
        $this->assertSame('admin', $first->actor_type);
        $this->assertSame($this->group, (int) $first->group_id);
        $this->assertSame('Sukma Support', json_decode((string) $first->metadata, true)['admin_name']);
        $this->assertSame('GET /api/v1/admin/support/group', json_decode((string) $first->metadata, true)['request']);

        $log = $this->asMember($this->holdingAdmin, 'GET', 'activity?filter[action]=group.support_read')->assertOk();
        $this->assertCount(8, $log->json('data'));
        $this->assertSame('admin', $log->json('data.0.actor.type'));
        $this->assertSame('Sukma Support', $log->json('data.0.metadata.admin_name'));
        $this->assertArrayNotHasKey('ip_address', $log->json('data.0'));
    }

    public function test_support_access_never_changes_anything(): void
    {
        $this->grant();

        $this->asAdmin($this->support, 'POST', "support/grades?group_id={$this->group}", ['code' => 'G1', 'name' => 'Staff', 'rank' => 1])->assertStatus(405);
        $this->asAdmin($this->support, 'PATCH', "support/settings?group_id={$this->group}", [])->assertStatus(404);
        $this->assertSame(0, DB::table('organization_grades')->count());
    }

    public function test_the_access_ends_at_its_time(): void
    {
        $this->grant(2);
        $this->read('group')->assertOk();

        Carbon::setTestNow('2026-10-16 11:00:01');
        $this->read('group')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:support-access-required');
        $this->asAdmin($this->support, 'GET', 'support/groups')->assertOk()->assertJsonCount(0, 'data');
        $this->asMember($this->holdingAdmin, 'GET', 'support-grants')->assertJsonPath('data.0.status', 'expired');
    }

    public function test_a_group_admin_revokes_the_access_at_once_and_a_new_grant_can_follow(): void
    {
        $id = $this->grant();
        $this->read('group')->assertOk();

        $this->asMember($this->viewer, 'DELETE', "support-grants/{$id}")->assertForbidden();
        $this->asMember($this->holdingAdmin, 'DELETE', "support-grants/{$id}")->assertNoContent();
        $this->read('group')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:support-access-required');
        $this->asMember($this->holdingAdmin, 'DELETE', "support-grants/{$id}")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:support-grant-not-active');
        $this->asMember($this->holdingAdmin, 'GET', 'support-grants')->assertJsonPath('data.0.status', 'revoked');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'group.support_revoked')->count());

        $this->grant(1);
        $this->read('group')->assertOk();
        $this->asMember($this->holdingAdmin, 'DELETE', 'support-grants/999999')->assertNotFound();
    }

    public function test_a_grant_of_one_group_does_not_open_another(): void
    {
        $this->grant();

        $other = $this->asAdmin($this->operator, 'POST', 'groups', ['name' => 'Grup Lain', 'legal_entity' => ['legal_name' => 'PT Lain', 'legal_form' => 'pt']])->assertCreated();
        $this->asAdmin($this->support, 'GET', 'support/group?group_id='.$other->json('data.id'))->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:support-access-required');
        $this->asAdmin($this->support, 'GET', 'support/group?group_id=999999')->assertForbidden();
    }
}
