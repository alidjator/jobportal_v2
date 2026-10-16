<?php

namespace Tests\Feature\Organization;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/erp-governance.md` section 3: `group_id` on audit entries and the group activity log (`group.audit.view`). */
final class GroupActivityTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private ?string $adminToken = null;

    private int $group;

    private int $holding;

    private int $holdingAdmin;

    private int $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->holding = $this->companyRowId('pt-holding');
        $this->holdingAdmin = $this->member($this->holding, 'hana@example.test', 'company_admin');
        $this->auditor = $this->member($this->holding, 'adi@example.test', 'recruiter');

        $created = $this->admin('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding Nusantara', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $entity = (int) $created->json('data.legal_entities.0.id');
        $this->admin('PUT', "groups/{$this->group}/legal-entities/{$entity}/tenant", ['company_id' => $this->holding])->assertOk();
        $this->admin('PUT', "groups/{$this->group}/members/{$this->holdingAdmin}", ['permissions' => ['group.structure.manage', 'group.audit.view']])->assertOk();
        $this->admin('PUT', "groups/{$this->group}/members/{$this->auditor}", ['permissions' => ['group.recruitment.view']])->assertOk();
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

    private function asMember(int $userId, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $token = UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        return $this->withToken($token)->json($method, "/api/v1/organization/{$uri}", $body);
    }

    private function log(string $query = ''): TestResponse
    {
        return $this->asMember($this->holdingAdmin, 'GET', 'activity'.$query)->assertOk();
    }

    private function record(int $userId, string $action, string $ip = '203.0.113.9'): void
    {
        $this->app->make(AuditTrail::class)->record(new AuditEntry(AuditActorType::User, $userId, $action, null, null, [], $ip, 'Mozilla/5.0', new DateTimeImmutable('2026-10-16 09:00:00')));
    }

    // ----- group_id on the entries -----

    public function test_entries_get_the_group_of_their_subject_or_of_the_acting_members_company(): void
    {
        $this->record($this->holdingAdmin, 'login');
        $outsider = $this->member($this->companyRowId('pt-luar'), 'luar@example.test', 'company_admin');
        $this->record($outsider, 'login');

        $this->assertSame($this->group, (int) DB::table('platform_audit_logs')->where('action', 'group.created')->value('group_id'), 'about the group');
        $this->assertSame($this->group, (int) DB::table('platform_audit_logs')->where('action', 'group.tenant_linked')->value('group_id'));
        $this->assertSame($this->group, (int) DB::table('platform_audit_logs')->where('action', 'login')->where('actor_id', $this->holdingAdmin)->value('group_id'), 'a member of an internal tenant');
        $this->assertNull(DB::table('platform_audit_logs')->where('action', 'login')->where('actor_id', $outsider)->value('group_id'), 'an external tenant has no group');
    }

    // ----- the group activity log -----

    public function test_the_log_shows_the_group_its_tenants_and_the_platform_without_addresses(): void
    {
        $this->record($this->auditor, 'login');
        $this->asMember($this->holdingAdmin, 'POST', 'grades', ['code' => 'G1', 'name' => 'Staff', 'rank' => 1])->assertCreated();

        $rows = $this->log()->json('data');
        $actions = array_column($rows, 'action');

        $this->assertContains('group.created', $actions);
        $this->assertContains('group.grade_created', $actions);
        $this->assertContains('login', $actions);
        $this->assertSame('group.grade_created', $rows[0]['action'], 'newest first');

        $grade = $rows[0];
        $this->assertSame('user', $grade['actor']['type']);
        $this->assertSame('Hana', $grade['actor']['name']);
        $this->assertSame($this->holding, $grade['company_id']);
        $this->assertSame('PT-HOLDING', $grade['company_name']);

        $created = collect($rows)->firstWhere('action', 'group.created');
        $this->assertSame('admin', $created['actor']['type']);
        $this->assertNull($created['actor']['name']);

        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('ip_address', $row);
            $this->assertArrayNotHasKey('user_agent', $row);
        }
    }

    public function test_the_log_needs_group_audit_view_and_never_shows_another_group_or_an_external_tenant(): void
    {
        $this->asMember($this->auditor, 'GET', 'activity')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:group-permission-required');
        $stranger = $this->member($this->companyRowId('pt-luar'), 'luar@example.test', 'company_admin');
        $this->asMember($stranger, 'GET', 'activity')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-group-member');

        $this->record($stranger, 'stranger.action');
        $other = $this->admin('POST', 'groups', ['name' => 'Grup Lain', 'legal_entity' => ['legal_name' => 'PT Lain', 'legal_form' => 'pt']])->assertCreated();
        $otherCompany = $this->companyRowId('pt-lain');
        $otherAdmin = $this->member($otherCompany, 'lain@example.test', 'company_admin');
        $this->admin('PUT', "groups/{$other->json('data.id')}/legal-entities/{$other->json('data.legal_entities.0.id')}/tenant", ['company_id' => $otherCompany])->assertOk();
        $this->record($otherAdmin, 'other.action');

        $actions = array_column($this->log('?per_page=100')->json('data'), 'action');
        $this->assertNotContains('stranger.action', $actions);
        $this->assertNotContains('other.action', $actions);
    }

    public function test_the_log_filters_by_member_tenant_action_and_time_and_pages_by_cursor(): void
    {
        $this->record($this->auditor, 'login');
        $this->record($this->auditor, 'logout');

        $this->assertSame(['login'], array_unique(array_column($this->log('?filter[action]=login')->json('data'), 'action')));
        $this->assertSame([$this->auditor], array_unique(array_column(array_column($this->log("?filter[user_id]={$this->auditor}")->json('data'), 'actor'), 'id')));
        $this->assertSame([$this->holding], array_unique(array_column($this->log("?filter[company_id]={$this->holding}")->json('data'), 'company_id')));
        $this->log('?filter[from]=2026-10-17T00:00:00Z')->assertJsonCount(0, 'data');
        $this->asMember($this->holdingAdmin, 'GET', 'activity?filter[ip]=1.2.3.4')->assertStatus(422);

        $first = $this->log('?per_page=2')->assertJsonCount(2, 'data');
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);
        $second = $this->log('?per_page=2&cursor='.$cursor);
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
    }
}
