<?php

namespace Tests\Feature\Organization;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\Company\CompanyDirectory;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/erp-organization.md` sections 2, 3, 4 and 7: groups, legal entities, the tenant link, group roles and the group status. */
final class GroupTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private ?string $adminToken = null;

    private int $holdingCompany;

    private int $subsidiaryCompany;

    private int $holdingAdmin;

    private int $subsidiaryMember;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->holdingCompany = $this->companyRowId('pt-holding');
        $this->subsidiaryCompany = $this->companyRowId('pt-anak');
        $this->holdingAdmin = $this->member($this->holdingCompany, 'hana@example.test', 'Hana Holding', 'company_admin');
        $this->subsidiaryMember = $this->member($this->subsidiaryCompany, 'sari@example.test', 'Sari Anak', 'company_admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ----- helpers -----

    private function member(int $companyId, string $email, string $name, string $role = 'recruiter'): int
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $name, 'email' => $email, 'password' => Hash::make('password-123'), 'status' => 'active', 'locale' => 'en',
            'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return (int) $user->id;
    }

    private function tokenOf(int $userId): string
    {
        return UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    private function adminToken(): string
    {
        if ($this->adminToken !== null) {
            return $this->adminToken;
        }

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['companies.manage']);

        return $this->adminToken = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->adminToken())->json($method, "/api/v1/admin/{$uri}", $body);
    }

    /** @param  array<string, mixed>  $body */
    private function asMember(int $userId, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->tokenOf($userId))->json($method, "/api/v1/organization/{$uri}", $body);
    }

    /** @return array<string, mixed> */
    private function entityBody(string $name = 'PT Holding Nusantara', array $more = []): array
    {
        return ['legal_name' => $name, 'legal_form' => 'pt', ...$more];
    }

    /** @return array{group: int, entity: int} a group whose holding entity is linked to the holding company */
    private function linkedGroup(): array
    {
        $created = $this->admin('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => $this->entityBody()])->assertCreated();
        $group = (int) $created->json('data.id');
        $entity = (int) $created->json('data.legal_entities.0.id');

        $this->admin('PUT', "groups/{$group}/legal-entities/{$entity}/tenant", ['company_id' => $this->holdingCompany])->assertOk();

        return ['group' => $group, 'entity' => $entity];
    }

    /** @return array{group: int, entity: int} a linked group where the holding admin holds every group permission */
    private function groupWithAdmin(): array
    {
        $ids = $this->linkedGroup();
        $this->admin('PUT', "groups/{$ids['group']}/members/{$this->holdingAdmin}", ['permissions' => ['group.structure.manage', 'group.members.manage', 'group.recruitment.view']])->assertOk();

        return $ids;
    }

    // ----- the platform admin creates a group -----

    public function test_a_platform_admin_creates_a_group_with_its_first_legal_entity(): void
    {
        $response = $this->admin('POST', 'groups', ['name' => ' Grup Nusantara ', 'legal_entity' => $this->entityBody(more: ['npwp' => '01.234.567.8-901.000', 'nib' => '1234567890123', 'address_line' => 'Jl. Merdeka 1'])])->assertCreated();

        $response->assertJsonPath('data.name', 'Grup Nusantara')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.member_count', 0)
            ->assertJsonPath('data.legal_entities.0.legal_name', 'PT Holding Nusantara')
            ->assertJsonPath('data.legal_entities.0.legal_form', 'pt')
            ->assertJsonPath('data.legal_entities.0.npwp', '01.234.567.8-901.000')
            ->assertJsonPath('data.legal_entities.0.status', 'active')
            ->assertJsonPath('data.legal_entities.0.tenant', null);

        $group = (int) $response->json('data.id');
        $entry = DB::table('platform_audit_logs')->where('action', 'group.created')->first();
        $this->assertSame($group, (int) $entry->subject_id);
        $this->assertSame('admin', $entry->actor_type);

        $this->admin('GET', "groups/{$group}")->assertOk()->assertJsonPath('data.status_reason', null)->assertJsonPath('data.suspended_at', null)->assertJsonMissingPath('data.my_permissions');
        $this->admin('GET', 'groups')->assertOk()->assertJsonPath('data.0.name', 'Grup Nusantara')->assertJsonPath('data.0.legal_entity_count', 1)->assertJsonPath('data.0.member_count', 0);
    }

    public function test_a_group_name_is_unique_whatever_the_letter_case_and_the_input_is_validated(): void
    {
        $this->admin('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => $this->entityBody()])->assertCreated();

        $this->admin('POST', 'groups', ['name' => 'GRUP NUSANTARA', 'legal_entity' => $this->entityBody('Other')])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:group-name-taken');
        $this->admin('POST', 'groups', ['name' => 'X', 'legal_entity' => ['legal_name' => 'A', 'legal_form' => 'llc']])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'legal_entity.legal_name', 'legal_entity.legal_form']);
        $this->admin('POST', 'groups', ['name' => 'No entity'])->assertStatus(422)->assertJsonValidationErrors(['legal_entity']);
        $this->admin('POST', 'groups', ['name' => 'Bad place', 'legal_entity' => $this->entityBody(more: ['city_id' => 999999])])->assertStatus(422);
    }

    public function test_only_a_platform_admin_with_companies_manage_reaches_the_group_routes(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/admin/groups')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenOf($this->holdingAdmin))->getJson('/api/v1/admin/groups')->assertUnauthorized();

        $bare = AdminModel::query()->create(['name' => 'Bare', 'email' => 'bare@example.test', 'password' => 'hash', 'status' => 'active']);
        $this->adminToken();
        $this->app['auth']->forgetGuards();
        $this->withToken($bare->createToken('console', ['*'], now()->addHour())->plainTextToken)->getJson('/api/v1/admin/groups')->assertForbidden();
    }

    // ----- the tenant link -----

    public function test_a_linked_company_is_an_internal_tenant_and_counts_as_verified(): void
    {
        $directory = $this->app->make(CompanyDirectory::class);
        $this->assertFalse($directory->isVerified($this->holdingCompany));

        $ids = $this->linkedGroup();

        $this->assertTrue($directory->isVerified($this->holdingCompany));
        $this->assertSame($ids['entity'], (int) DB::table('company_companies')->where('id', $this->holdingCompany)->value('legal_entity_id'));
        $this->assertNull(DB::table('company_companies')->where('id', $this->holdingCompany)->value('verified_at'), 'derived, not stored');

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/companies/pt-holding')->assertOk()->assertJsonPath('data.verified', true)->assertJsonPath('data.verified_at', null);

        $this->admin('GET', "groups/{$ids['group']}")->assertJsonPath('data.legal_entities.0.tenant.company_id', $this->holdingCompany)
            ->assertJsonPath('data.legal_entities.0.tenant.status', 'active');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'group.tenant_linked')->count());
    }

    public function test_the_company_list_tells_the_companies_that_are_linked_from_those_that_are_free(): void
    {
        $ids = $this->linkedGroup();
        $listed = fn (string $linked): array => array_map('intval', array_column($this->admin('GET', "companies?filter[linked]={$linked}&per_page=100")->assertOk()->json('data'), 'id'));

        $this->assertSame([$this->holdingCompany], $listed('true'));
        $this->assertNotContains($this->holdingCompany, $listed('false'));
        $this->assertContains($this->subsidiaryCompany, $listed('false'));
        $this->admin('GET', 'companies?filter[linked]=maybe')->assertStatus(422);

        $this->admin('DELETE', "groups/{$ids['group']}/legal-entities/{$ids['entity']}/tenant")->assertNoContent();
        $this->assertContains($this->holdingCompany, $listed('false'));
    }

    public function test_the_group_level_is_off_on_the_portal_of_one_company_but_the_structure_and_the_setup_stay(): void
    {
        $ids = $this->groupWithAdmin();
        $off = fn (string $uri) => $this->asMember($this->holdingAdmin, 'GET', $uri)->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:groups-unavailable');

        $this->asMember($this->holdingAdmin, 'GET', 'members')->assertOk();
        $this->asMember($this->holdingAdmin, 'GET', 'units')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:legal-entity-required');
        $other = (int) $this->asMember($this->holdingAdmin, 'POST', 'legal-entities', $this->entityBody('PT Lain Lagi', ['legal_form' => 'cv']))->assertCreated()->json('data.id');

        DB::table('platform_portal_identity')->insert(['mode' => 'company', 'scope' => 'company', 'company_ids' => json_encode([$this->holdingCompany]), 'updated_at' => now()]);

        foreach (['members', 'settings', 'portal', 'activity', 'tenants', 'support-grants'] as $uri) {
            $off($uri);
        }

        // Changing the legal entities is the group's work and closes; reading the group, the structure and the platform admin's pages to set them up stay.
        $this->asMember($this->holdingAdmin, 'POST', 'legal-entities', $this->entityBody('PT Baru'))->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:groups-unavailable');
        $this->asMember($this->holdingAdmin, 'PATCH', "legal-entities/{$ids['entity']}", ['legal_name' => 'PT Lain'])->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:groups-unavailable');
        $this->asMember($this->holdingAdmin, 'POST', "legal-entities/{$ids['entity']}/deactivate")->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:groups-unavailable');
        $this->asMember($this->holdingAdmin, 'POST', "legal-entities/{$ids['entity']}/reactivate")->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:groups-unavailable');
        $off('group');

        // The group permissions of the member come with `GET company/me`; the structure uses the one legal entity of the company, by itself, and no other one is reachable.
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenOf($this->holdingAdmin))->getJson('/api/v1/company/me')->assertOk()->assertJsonPath('data.group.name', 'Grup Nusantara')
            ->assertJsonPath('data.group.permissions', ['group.structure.manage', 'group.members.manage', 'group.recruitment.view']);
        $this->asMember($this->holdingAdmin, 'GET', 'units')->assertOk();
        $this->asMember($this->holdingAdmin, 'GET', 'positions')->assertOk();
        $this->asMember($this->holdingAdmin, 'GET', 'cost-centres')->assertOk();
        $this->asMember($this->holdingAdmin, 'GET', 'units?legal_entity_id='.$ids['entity'])->assertOk();
        $this->asMember($this->holdingAdmin, 'GET', "units?legal_entity_id={$other}")->assertNotFound();
        $this->asMember($this->holdingAdmin, 'GET', 'unit-types')->assertOk();
        $this->admin('GET', 'groups')->assertOk();

        DB::table('platform_portal_identity')->update(['scope' => 'group']);
        $this->asMember($this->holdingAdmin, 'GET', 'members')->assertOk();
        $this->asMember($this->holdingAdmin, 'GET', 'group')->assertOk();
        $this->asMember($this->holdingAdmin, 'GET', 'units')->assertStatus(422);
        $this->asMember($this->holdingAdmin, 'POST', 'legal-entities', $this->entityBody('PT Baru'))->assertCreated();

        DB::table('platform_portal_identity')->delete();
        $this->asMember($this->holdingAdmin, 'GET', 'members')->assertOk();
    }

    public function test_a_single_installation_links_no_second_tenant(): void
    {
        $ids = $this->groupWithAdmin();
        $anak = (int) $this->asMember($this->holdingAdmin, 'POST', 'legal-entities', $this->entityBody('PT Anak Usaha', ['legal_form' => 'cv']))->assertCreated()->json('data.id');
        $link = fn () => $this->admin('PUT', "groups/{$ids['group']}/legal-entities/{$anak}/tenant", ['company_id' => $this->subsidiaryCompany]);

        DB::table('platform_portal_identity')->insert(['mode' => 'company', 'scope' => 'company', 'company_ids' => json_encode([$this->holdingCompany]), 'updated_at' => now()]);
        $link()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:single-company-installation');
        $this->assertNull(DB::table('company_companies')->where('id', $this->subsidiaryCompany)->value('legal_entity_id'));

        DB::table('platform_portal_identity')->update(['scope' => 'group']);
        $link()->assertOk();
    }

    public function test_one_entity_has_one_tenant_and_one_company_one_entity(): void
    {
        $ids = $this->linkedGroup();
        $second = (int) $this->admin('POST', 'groups', ['name' => 'Grup Lain', 'legal_entity' => $this->entityBody('PT Lain')])->assertCreated()->json('data.id');
        $otherEntity = (int) DB::table('organization_legal_entities')->where('group_id', $second)->value('id');

        $this->admin('PUT', "groups/{$ids['group']}/legal-entities/{$ids['entity']}/tenant", ['company_id' => $this->subsidiaryCompany])->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:legal-entity-has-tenant');
        $this->admin('PUT', "groups/{$second}/legal-entities/{$otherEntity}/tenant", ['company_id' => $this->holdingCompany])->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:company-already-linked');
        $this->admin('PUT', "groups/{$second}/legal-entities/{$otherEntity}/tenant", ['company_id' => 999999])->assertNotFound();
        $this->admin('PUT', "groups/{$second}/legal-entities/{$ids['entity']}/tenant", ['company_id' => $this->subsidiaryCompany])->assertNotFound();
        $this->admin('PUT', "groups/{$second}/legal-entities/{$otherEntity}/tenant", [])->assertStatus(422)->assertJsonValidationErrors(['company_id']);
    }

    public function test_a_link_can_be_removed_only_while_no_group_role_depends_on_it(): void
    {
        $ids = $this->groupWithAdmin();

        $this->admin('DELETE', "groups/{$ids['group']}/legal-entities/{$ids['entity']}/tenant")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:tenant-link-in-use');

        $this->admin('DELETE', "groups/{$ids['group']}/members/{$this->holdingAdmin}")->assertNoContent();
        $this->admin('DELETE', "groups/{$ids['group']}/legal-entities/{$ids['entity']}/tenant")->assertNoContent();

        $this->assertNull(DB::table('company_companies')->where('id', $this->holdingCompany)->value('legal_entity_id'));
        $this->assertFalse($this->app->make(CompanyDirectory::class)->isVerified($this->holdingCompany));
        $this->admin('DELETE', "groups/{$ids['group']}/legal-entities/{$ids['entity']}/tenant")->assertNotFound();
    }

    // ----- group roles -----

    public function test_a_platform_admin_gives_the_first_group_role_to_a_member_of_a_tenant_of_the_group(): void
    {
        $ids = $this->linkedGroup();

        $this->admin('PUT', "groups/{$ids['group']}/members/{$this->holdingAdmin}", ['permissions' => ['group.recruitment.view', 'group.structure.manage']])->assertOk()
            ->assertJsonPath('data.user_id', $this->holdingAdmin)
            ->assertJsonPath('data.name', 'Hana Holding')
            ->assertJsonPath('data.company', ['id' => $this->holdingCompany, 'name' => 'PT-HOLDING'])
            ->assertJsonPath('data.permissions', ['group.structure.manage', 'group.recruitment.view']);

        $this->admin('GET', "groups/{$ids['group']}/members")->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'group.role_granted')->count());

        $this->admin('PUT', "groups/{$ids['group']}/members/{$this->holdingAdmin}", ['permissions' => ['group.members.manage']])->assertOk()->assertJsonPath('data.permissions', ['group.members.manage']);
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'group.role_changed')->count());
        $this->assertSame(1, DB::table('organization_group_members')->count());
    }

    public function test_a_group_role_needs_a_tenant_of_the_group_a_valid_permission_list_and_one_group(): void
    {
        $ids = $this->linkedGroup();
        $seeker = (int) UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en'])->id;

        $this->admin('PUT', "groups/{$ids['group']}/members/{$this->subsidiaryMember}", ['permissions' => ['group.members.manage']])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:user-not-in-group');
        $this->admin('PUT', "groups/{$ids['group']}/members/{$seeker}", ['permissions' => ['group.members.manage']])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:user-not-in-group');
        $this->admin('PUT', "groups/{$ids['group']}/members/{$this->holdingAdmin}", ['permissions' => []])->assertStatus(422)->assertJsonValidationErrors(['permissions']);
        $this->admin('PUT', "groups/{$ids['group']}/members/{$this->holdingAdmin}", ['permissions' => ['group.nothing']])->assertStatus(422)->assertJsonValidationErrors(['permissions.0']);
        $this->admin('PUT', "groups/999999/members/{$this->holdingAdmin}", ['permissions' => ['group.members.manage']])->assertNotFound();
        $this->assertSame(0, DB::table('organization_group_members')->count());

        $second = (int) $this->admin('POST', 'groups', ['name' => 'Grup Lain', 'legal_entity' => $this->entityBody('PT Lain')])->assertCreated()->json('data.id');
        $otherEntity = (int) DB::table('organization_legal_entities')->where('group_id', $second)->value('id');
        $this->admin('PUT', "groups/{$second}/legal-entities/{$otherEntity}/tenant", ['company_id' => $this->subsidiaryCompany])->assertOk();
        $this->admin('PUT', "groups/{$ids['group']}/members/{$this->holdingAdmin}", ['permissions' => ['group.members.manage']])->assertOk();
        $this->admin('PUT', "groups/{$second}/members/{$this->holdingAdmin}", ['permissions' => ['group.members.manage']])->assertStatus(422);
    }

    public function test_the_role_ends_with_the_company_membership(): void
    {
        $this->groupWithAdmin();

        Event::dispatch(new CompanyMemberRemoved($this->holdingCompany, $this->holdingAdmin, 'company_admin', $this->holdingAdmin, null, null, null, now()->toDateTimeImmutable()));

        $this->assertSame(0, DB::table('organization_group_members')->count());
        $this->assertSame(0, DB::table('organization_group_member_permissions')->count());
    }

    // ----- the group side -----

    public function test_a_group_member_reads_the_group_with_their_own_permissions(): void
    {
        $ids = $this->groupWithAdmin();

        $this->asMember($this->holdingAdmin, 'GET', 'group')->assertOk()
            ->assertJsonPath('data.id', $ids['group'])
            ->assertJsonPath('data.my_permissions', ['group.structure.manage', 'group.members.manage', 'group.recruitment.view'])
            ->assertJsonPath('data.legal_entities.0.tenant.company_id', $this->holdingCompany)
            ->assertJsonMissingPath('data.status_reason')
            ->assertJsonMissingPath('data.suspended_at');

        $this->asMember($this->subsidiaryMember, 'GET', 'group')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-group-member');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/organization/group')->assertUnauthorized();
    }

    public function test_a_group_admin_adds_edits_deactivates_and_reactivates_legal_entities(): void
    {
        $ids = $this->groupWithAdmin();

        $created = $this->asMember($this->holdingAdmin, 'POST', 'legal-entities', $this->entityBody('PT Anak Usaha', ['legal_form' => 'cv']))->assertCreated();
        $anak = (int) $created->json('data.id');
        $created->assertJsonPath('data.legal_form', 'cv')->assertJsonPath('data.tenant', null);

        $this->asMember($this->holdingAdmin, 'PATCH', "legal-entities/{$anak}", ['legal_name' => 'PT Anak Baru', 'nib' => '99', 'npwp' => null])->assertOk()
            ->assertJsonPath('data.legal_name', 'PT Anak Baru')->assertJsonPath('data.nib', '99')->assertJsonPath('data.legal_form', 'cv');
        $this->asMember($this->holdingAdmin, 'PATCH', "legal-entities/{$anak}", ['legal_name' => 'PT HOLDING NUSANTARA'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:legal-entity-name-taken');
        $this->asMember($this->holdingAdmin, 'PATCH', "legal-entities/{$anak}", ['city_id' => 999999])->assertStatus(422);
        $this->asMember($this->holdingAdmin, 'PATCH', "legal-entities/{$anak}", ['legal_form' => 'llc'])->assertStatus(422)->assertJsonValidationErrors(['legal_form']);

        $this->asMember($this->holdingAdmin, 'POST', "legal-entities/{$anak}/deactivate")->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->asMember($this->holdingAdmin, 'POST', "legal-entities/{$anak}/deactivate")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:legal-entity-already-inactive');
        $this->asMember($this->holdingAdmin, 'POST', "legal-entities/{$anak}/reactivate")->assertOk()->assertJsonPath('data.status', 'active');
        $this->asMember($this->holdingAdmin, 'POST', "legal-entities/{$anak}/reactivate")->assertStatus(409);

        foreach (['group.legal_entity_created', 'group.legal_entity_updated', 'group.legal_entity_deactivated', 'group.legal_entity_reactivated'] as $action) {
            $this->assertGreaterThanOrEqual(1, DB::table('platform_audit_logs')->where('action', $action)->count(), $action);
        }

        $this->admin('GET', "groups/{$ids['group']}")->assertJsonCount(2, 'data.legal_entities');
    }

    public function test_a_legal_entity_with_an_active_tenant_is_not_deactivated_and_another_groups_entity_is_not_found(): void
    {
        $ids = $this->groupWithAdmin();
        $other = (int) $this->admin('POST', 'groups', ['name' => 'Grup Lain', 'legal_entity' => $this->entityBody('PT Lain')])->assertCreated()->json('data.legal_entities.0.id');

        $this->asMember($this->holdingAdmin, 'POST', "legal-entities/{$ids['entity']}/deactivate")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:legal-entity-has-active-tenant');

        DB::table('company_companies')->where('id', $this->holdingCompany)->update(['status' => 'suspended']);
        $this->asMember($this->holdingAdmin, 'POST', "legal-entities/{$ids['entity']}/deactivate")->assertOk();

        $this->asMember($this->holdingAdmin, 'PATCH', "legal-entities/{$other}", ['legal_name' => 'Hijack'])->assertNotFound();
        $this->asMember($this->holdingAdmin, 'POST', "legal-entities/{$other}/deactivate")->assertNotFound();
        $this->assertSame('PT Lain', DB::table('organization_legal_entities')->where('id', $other)->value('legal_name'));
    }

    public function test_the_group_permissions_decide_what_a_member_may_do(): void
    {
        $ids = $this->groupWithAdmin();
        $colleague = $this->member($this->holdingCompany, 'cici@example.test', 'Cici Colleague');
        $this->admin('PUT', "groups/{$ids['group']}/members/{$colleague}", ['permissions' => ['group.recruitment.view']])->assertOk();

        $this->asMember($colleague, 'GET', 'group')->assertOk()->assertJsonPath('data.my_permissions', ['group.recruitment.view']);
        $this->asMember($colleague, 'POST', 'legal-entities', $this->entityBody('PT Nope'))->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:group-permission-required');
        $this->asMember($colleague, 'GET', 'members')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:group-permission-required');
        $this->asMember($colleague, 'PUT', "members/{$this->holdingAdmin}", ['permissions' => ['group.members.manage']])->assertForbidden();
        $this->assertSame(1, DB::table('organization_legal_entities')->count());
    }

    public function test_a_group_admin_manages_the_roles_of_their_own_group_but_never_their_own(): void
    {
        $this->groupWithAdmin();
        $colleague = $this->member($this->holdingCompany, 'cici@example.test', 'Cici Colleague');

        $this->asMember($this->holdingAdmin, 'PUT', "members/{$colleague}", ['permissions' => ['group.recruitment.view']])->assertOk()->assertJsonPath('data.name', 'Cici Colleague');
        $this->asMember($this->holdingAdmin, 'GET', 'members')->assertOk()->assertJsonCount(2, 'data');

        $this->asMember($this->holdingAdmin, 'PUT', "members/{$this->holdingAdmin}", ['permissions' => ['group.recruitment.view']])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:cannot-change-own-group-role');
        $this->asMember($this->holdingAdmin, 'DELETE', "members/{$this->holdingAdmin}")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:cannot-change-own-group-role');
        $this->asMember($this->holdingAdmin, 'PUT', "members/{$this->subsidiaryMember}", ['permissions' => ['group.recruitment.view']])->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:user-not-in-group');

        $this->asMember($this->holdingAdmin, 'DELETE', "members/{$colleague}")->assertNoContent();
        $this->asMember($this->holdingAdmin, 'DELETE', "members/{$colleague}")->assertNotFound();
        $this->asMember($colleague, 'GET', 'group')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-group-member');
    }

    // ----- the group status -----

    public function test_suspending_a_group_suspends_its_tenants_and_refuses_its_roles_and_restoring_brings_back_only_those_tenants(): void
    {
        $ids = $this->groupWithAdmin();
        // A second tenant of the group, suspended on its own before the group is.
        $entity = (int) DB::table('organization_legal_entities')->insertGetId([
            'group_id' => $ids['group'], 'legal_name' => 'PT Anak', 'legal_form' => 'pt', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->admin('PUT', "groups/{$ids['group']}/legal-entities/{$entity}/tenant", ['company_id' => $this->subsidiaryCompany])->assertOk();
        DB::table('company_companies')->where('id', $this->subsidiaryCompany)->update(['status' => 'suspended', 'suspended_at' => now(), 'suspension_reason' => 'Own reason']);

        $this->admin('POST', "groups/{$ids['group']}/suspend", ['reason' => 'Contract ended'])->assertOk()
            ->assertJsonPath('data.status', 'suspended')->assertJsonPath('data.status_reason', 'Contract ended');

        $holding = DB::table('company_companies')->where('id', $this->holdingCompany)->first();
        $this->assertSame('suspended', $holding->status);
        $this->assertSame('Contract ended', $holding->suspension_reason);
        $this->assertNotNull($holding->suspended_by_group_at);
        $this->assertNull(DB::table('company_companies')->where('id', $this->subsidiaryCompany)->value('suspended_by_group_at'), 'a tenant suspended on its own is not marked');

        $this->asMember($this->holdingAdmin, 'GET', 'group')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:group-suspended');
        $this->admin('POST', "groups/{$ids['group']}/suspend", ['reason' => 'Again again'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:group-already-suspended');
        $this->admin('PUT', "groups/{$ids['group']}/legal-entities/{$ids['entity']}/tenant", ['company_id' => $this->holdingCompany])->assertStatus(409);

        $this->admin('POST', "groups/{$ids['group']}/restore")->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.status_reason', null);

        $this->assertSame('active', DB::table('company_companies')->where('id', $this->holdingCompany)->value('status'));
        $this->assertNull(DB::table('company_companies')->where('id', $this->holdingCompany)->value('suspended_by_group_at'));
        $this->assertSame('suspended', DB::table('company_companies')->where('id', $this->subsidiaryCompany)->value('status'), 'a tenant suspended on its own stays suspended');
        $this->assertSame('Own reason', DB::table('company_companies')->where('id', $this->subsidiaryCompany)->value('suspension_reason'));

        $this->asMember($this->holdingAdmin, 'GET', 'group')->assertOk();
        $this->admin('POST', "groups/{$ids['group']}/restore")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:group-not-suspended');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'group.suspended')->count());
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'group.restored')->count());
    }

    public function test_a_suspension_needs_a_reason(): void
    {
        $ids = $this->linkedGroup();

        $this->admin('POST', "groups/{$ids['group']}/suspend", ['reason' => 'no'])->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->admin('POST', 'groups/999999/suspend', ['reason' => 'Contract ended'])->assertNotFound();
        $this->assertSame('active', DB::table('organization_groups')->where('id', $ids['group'])->value('status'));
    }
}
