<?php

namespace Tests\Feature\Approval;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Approval\Application\Flow\ApprovalFlows;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\Approval\ApprovalFact;
use Src\Shared\Application\Approval\ApprovalSubjectCatalog;
use Src\Shared\Application\Approval\ApprovalSubjectType;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/erp-requisitions.md` section 4: the approval flows of a group (the default) and of a tenant (which replaces it), one per subject type. */
final class ApprovalFlowTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string TYPE = 'test.requisition';

    private int $holding;

    private int $group;

    private UserModel $admin;

    private UserModel $recruiter;

    private UserModel $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');

        // The subject types are registered by the contexts that own them (Workforce, later Recruitment); this stands in for one.
        $this->app->make(ApprovalSubjectCatalog::class)->add(new ApprovalSubjectType(
            self::TYPE, 'A requisition of a test.',
            [
                new ApprovalFact('hires', ApprovalFact::INTEGER, 'Number of hires'),
                new ApprovalFact('type', ApprovalFact::STRING, 'Type', ['planned', 'replacement', 'unplanned']),
                new ApprovalFact('urgent', ApprovalFact::BOOLEAN, 'Urgent'),
            ],
            ['hiring_manager'],
        ));

        $this->holding = $this->companyRowId('pt-holding');
        $this->admin = $this->member($this->holding, 'andi@example.test', 'company_admin');
        $this->recruiter = $this->member($this->holding, 'rina@example.test', 'recruiter');
        $this->outsider = $this->member($this->companyRowId('pt-luar'), 'eko@example.test', 'company_admin');

        $created = $this->operator('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $this->operator('PUT', "groups/{$this->group}/legal-entities/{$created->json('data.legal_entities.0.id')}/tenant", ['company_id' => $this->holding])->assertOk();
        $this->operator('PUT', "groups/{$this->group}/members/{$this->admin->id}", ['permissions' => ['group.approvals.manage']])->assertOk();
        $this->operator('PUT', "groups/{$this->group}/members/{$this->recruiter->id}", ['permissions' => ['group.recruitment.view']])->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(int $companyId, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('password-123'), 'status' => 'active', 'locale' => 'en',
            'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return $user;
    }

    /** @param  array<string, mixed>  $body */
    private function operator(string $method, string $uri, array $body = []): TestResponse
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->firstOrCreate(['email' => 'ops@example.test'], ['name' => 'Ops', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['companies.manage']);
        $this->app['auth']->forgetGuards();

        return $this->withToken($admin->createToken('console', ['*'], now()->addHour())->plainTextToken)->json($method, "/api/v1/admin/{$uri}", $body);
    }

    /** @param  array<string, mixed>  $body */
    private function as(UserModel $user, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('phone', ['*'], now()->addDay())->plainTextToken)->json($method, "/api/v1{$uri}", $body);
    }

    /** @return array<string, mixed> */
    private function flow(?int $memberId = null): array
    {
        return ['steps' => [
            ['mode' => 'any', 'approvers' => [['kind' => 'relative', 'value' => 'hiring_manager']]],
            [
                'mode' => 'all',
                'conditions' => [['fact' => 'hires', 'operator' => 'gte', 'value' => 3], ['fact' => 'type', 'operator' => 'in', 'value' => ['planned', 'unplanned']]],
                'approvers' => [['kind' => 'member', 'value' => (string) ($memberId ?? $this->recruiter->id)], ['kind' => 'group_role', 'value' => 'group.approvals.manage']],
            ],
        ]];
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('type', "urn:onesis:error:{$code}");
    }

    // ----- the group's default flows -----

    public function test_a_group_admin_saves_reads_and_removes_the_default_flow_and_it_is_audited(): void
    {
        $page = $this->as($this->admin, 'GET', '/organization/approval-flows')->assertOk();
        $page->assertJsonPath('data.0.subject_type', self::TYPE)->assertJsonPath('data.0.source', 'none')->assertJsonPath('data.0.flow', null)
            ->assertJsonPath('data.0.relative_roles', ['hiring_manager'])->assertJsonPath('data.0.facts.1.allowed_values', ['planned', 'replacement', 'unplanned']);

        $saved = $this->as($this->admin, 'PUT', '/organization/approval-flows/'.self::TYPE, $this->flow())->assertOk();
        $saved->assertJsonPath('data.0.source', 'group')->assertJsonPath('data.0.flow.steps.0.position', 1)->assertJsonPath('data.0.flow.steps.0.mode', 'any')
            ->assertJsonPath('data.0.flow.steps.1.conditions.0.value', 3)->assertJsonPath('data.0.flow.steps.1.conditions.1.value', ['planned', 'unplanned'])
            ->assertJsonPath('data.0.flow.steps.1.approvers.0.value', (string) $this->recruiter->id);

        $entry = DB::table('platform_audit_logs')->where('action', 'approval.flow_saved')->first();
        $this->assertSame($this->group, (int) $entry->group_id);
        $this->assertSame(2, json_decode((string) $entry->metadata, true)['steps']);

        $replaced = $this->flow();
        $replaced['steps'] = [$replaced['steps'][0]];
        $this->as($this->admin, 'PUT', '/organization/approval-flows/'.self::TYPE, $replaced)->assertOk()->assertJsonCount(1, 'data.0.flow.steps');
        $this->assertSame(1, DB::table('approval_flows')->count());
        $this->assertSame(1, DB::table('approval_flow_steps')->count(), 'the old steps are gone');

        $this->as($this->admin, 'DELETE', '/organization/approval-flows/'.self::TYPE)->assertNoContent();
        $this->as($this->admin, 'GET', '/organization/approval-flows')->assertJsonPath('data.0.source', 'none');
        $this->as($this->admin, 'DELETE', '/organization/approval-flows/'.self::TYPE)->assertNotFound();
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'approval.flow_removed')->count());
    }

    public function test_a_flow_is_checked_against_its_subject_type_and_its_scope(): void
    {
        $put = fn (array $body): TestResponse => $this->as($this->admin, 'PUT', '/organization/approval-flows/'.self::TYPE, $body);
        $with = function (array $step): array {
            return ['steps' => [[...['mode' => 'any', 'approvers' => [['kind' => 'relative', 'value' => 'hiring_manager']]], ...$step]]];
        };

        $this->as($this->admin, 'PUT', '/organization/approval-flows/no.such_type', $this->flow())->assertNotFound();
        $put(['steps' => []])->assertStatus(422)->assertJsonValidationErrors(['steps']);
        $put(['steps' => array_fill(0, 11, $this->flow()['steps'][0])])->assertStatus(422)->assertJsonValidationErrors(['steps']);
        $put($with(['mode' => 'some']))->assertStatus(422)->assertJsonValidationErrors(['steps.0.mode']);

        $this->error($put($with(['conditions' => [['fact' => 'colour', 'operator' => 'eq', 'value' => 'red']]])), 422, 'invalid-flow-condition');
        $this->error($put($with(['conditions' => [['fact' => 'hires', 'operator' => 'in', 'value' => [3]]]])), 422, 'invalid-flow-condition');
        $this->error($put($with(['conditions' => [['fact' => 'hires', 'operator' => 'gte', 'value' => 'three']]])), 422, 'invalid-flow-condition');
        $this->error($put($with(['conditions' => [['fact' => 'type', 'operator' => 'eq', 'value' => 'invented']]])), 422, 'invalid-flow-condition');
        $this->error($put($with(['conditions' => [['fact' => 'urgent', 'operator' => 'gte', 'value' => true]]])), 422, 'invalid-flow-condition');
        $put($with(['conditions' => [['fact' => 'urgent', 'operator' => 'eq', 'value' => true]]]))->assertOk();

        $this->error($put($with(['approvers' => [['kind' => 'relative', 'value' => 'ceo']]])), 422, 'invalid-flow-approver');
        $this->error($put($with(['approvers' => [['kind' => 'group_role', 'value' => 'group.nothing']]])), 422, 'invalid-flow-approver');
        $this->error($put($with(['approvers' => [['kind' => 'member', 'value' => (string) $this->outsider->id]]])), 422, 'invalid-flow-approver');
        $this->error($put($with(['approvers' => [['kind' => 'member', 'value' => (string) $this->recruiter->id], ['kind' => 'member', 'value' => (string) $this->recruiter->id]]])), 422, 'invalid-flow-approver');
        $put($with(['approvers' => [['kind' => 'member', 'value' => (string) $this->recruiter->id]]]))->assertOk();
    }

    public function test_the_default_flows_are_read_by_any_group_member_and_changed_by_the_approvals_manager_only(): void
    {
        $this->as($this->recruiter, 'GET', '/organization/approval-flows')->assertOk();
        $this->error($this->as($this->recruiter, 'PUT', '/organization/approval-flows/'.self::TYPE, $this->flow()), 403, 'group-permission-required');
        $this->error($this->as($this->recruiter, 'DELETE', '/organization/approval-flows/'.self::TYPE), 403, 'group-permission-required');
        $this->error($this->as($this->outsider, 'GET', '/organization/approval-flows'), 403, 'not-a-group-member');
        $this->assertSame(0, DB::table('approval_flows')->count());
    }

    // ----- a tenant's own flows -----

    public function test_a_tenant_replaces_the_group_default_for_itself_and_falls_back_when_it_removes_it(): void
    {
        $this->as($this->admin, 'PUT', '/organization/approval-flows/'.self::TYPE, $this->flow())->assertOk();

        $this->as($this->recruiter, 'GET', '/company/approval-flows')->assertOk()->assertJsonPath('data.0.source', 'group')->assertJsonCount(2, 'data.0.flow.steps');

        $own = ['steps' => [['mode' => 'all', 'approvers' => [['kind' => 'member', 'value' => (string) $this->recruiter->id]]]]];
        $this->as($this->admin, 'PUT', '/company/approval-flows/'.self::TYPE, $own)->assertOk()->assertJsonPath('data.0.source', 'company')->assertJsonCount(1, 'data.0.flow.steps');
        $this->as($this->recruiter, 'GET', '/company/approval-flows')->assertJsonPath('data.0.source', 'company');
        $this->as($this->admin, 'GET', '/organization/approval-flows')->assertJsonCount(2, 'data.0.flow.steps');

        $entry = DB::table('platform_audit_logs')->where('action', 'approval.flow_saved')->where('company_id', $this->holding)->latest('id')->first();
        $this->assertSame($this->group, (int) $entry->group_id, 'a tenant flow belongs to its group log too');

        $this->as($this->admin, 'DELETE', '/company/approval-flows/'.self::TYPE)->assertNoContent();
        $this->as($this->admin, 'GET', '/company/approval-flows')->assertJsonPath('data.0.source', 'group');
        $this->as($this->admin, 'DELETE', '/company/approval-flows/'.self::TYPE)->assertNotFound();
    }

    public function test_a_tenant_flow_needs_approvals_manage_and_an_internal_tenant_and_names_only_its_own_members(): void
    {
        $this->error($this->as($this->recruiter, 'PUT', '/company/approval-flows/'.self::TYPE, $this->flow()), 403, 'company-permission-required');
        $this->error($this->as($this->outsider, 'GET', '/company/approval-flows'), 403, 'not-an-internal-tenant');
        $this->error($this->as($this->outsider, 'PUT', '/company/approval-flows/'.self::TYPE, $this->flow($this->outsider->id)), 403, 'not-an-internal-tenant');

        $stranger = ['steps' => [['mode' => 'any', 'approvers' => [['kind' => 'member', 'value' => (string) $this->outsider->id]]]]];
        $this->error($this->as($this->admin, 'PUT', '/company/approval-flows/'.self::TYPE, $stranger), 422, 'invalid-flow-approver');
    }

    public function test_the_flow_that_applies_is_the_tenants_own_else_the_groups_else_none(): void
    {
        $flows = $this->app->make(ApprovalFlows::class);
        $this->assertNull($flows->resolve($this->holding, $this->group, self::TYPE));

        $this->as($this->admin, 'PUT', '/organization/approval-flows/'.self::TYPE, $this->flow())->assertOk();
        $this->assertCount(2, $flows->resolve($this->holding, $this->group, self::TYPE)->steps);

        $this->as($this->admin, 'PUT', '/company/approval-flows/'.self::TYPE, ['steps' => [['mode' => 'any', 'approvers' => [['kind' => 'relative', 'value' => 'hiring_manager']]]]])->assertOk();
        $this->assertCount(1, $flows->resolve($this->holding, $this->group, self::TYPE)->steps);
    }
}
