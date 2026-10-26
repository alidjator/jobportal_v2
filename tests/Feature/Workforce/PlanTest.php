<?php

namespace Tests\Feature\Workforce;

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

/** `docs/design/erp-requisitions.md` section 2: the manpower plans of a legal entity, their lines, versions and approval. */
final class PlanTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private int $holding;

    private int $group;

    private int $entity;

    private UserModel $admin;

    private UserModel $planner;

    private UserModel $viewer;

    private UserModel $reviewer;

    private UserModel $groupReader;

    private UserModel $nobody;

    private UserModel $outsider;

    private int $accountant;

    private int $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->holding = $this->companyRowId('pt-holding');
        $this->admin = $this->member($this->holding, 'andi@example.test', 'company_admin');
        $this->planner = $this->member($this->holding, 'pia@example.test', 'member', ['workforce.plans.manage']);
        $this->viewer = $this->member($this->holding, 'vira@example.test', 'member', ['workforce.plans.view']);
        $this->reviewer = $this->member($this->holding, 'rudi@example.test', 'member', []);
        $this->groupReader = $this->member($this->holding, 'gina@example.test', 'member', []);
        $this->nobody = $this->member($this->holding, 'nana@example.test', 'member', []);
        $this->outsider = $this->member($this->companyRowId('pt-luar'), 'eko@example.test', 'company_admin');

        $created = $this->operator('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $this->entity = (int) $created->json('data.legal_entities.0.id');
        $this->operator('PUT', "groups/{$this->group}/legal-entities/{$this->entity}/tenant", ['company_id' => $this->holding])->assertOk();
        $this->operator('PUT', "groups/{$this->group}/members/{$this->admin->id}", ['permissions' => ['group.structure.manage', 'group.approvals.manage', 'group.workforce.manage', 'group.settings.manage']])->assertOk();
        $this->operator('PUT', "groups/{$this->group}/members/{$this->groupReader->id}", ['permissions' => ['group.workforce.view']])->assertOk();

        $type = (int) $this->as($this->admin, 'POST', '/organization/unit-types', ['code' => 'DEPT', 'name' => 'Department', 'level_order' => 2])->assertCreated()->json('data.id');
        $unit = (int) $this->as($this->admin, 'POST', '/organization/units', ['legal_entity_id' => $this->entity, 'code' => 'FIN', 'name' => 'Finance', 'unit_type_id' => $type])->assertCreated()->json('data.id');
        $this->accountant = $this->position('ACC', $unit);
        $this->clerk = $this->position('CLK', $unit);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ----- helpers -----

    /** @param  ?list<string>  $permissions */
    private function member(int $companyId, string $email, string $role, ?array $permissions = null): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('password-123'), 'status' => 'active', 'locale' => 'en',
            'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role, $permissions);

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

    private function position(string $code, int $unit): int
    {
        return (int) $this->as($this->admin, 'POST', '/organization/positions', ['legal_entity_id' => $this->entity, 'code' => $code, 'title' => "Position {$code}", 'unit_id' => $unit])->assertCreated()->json('data.id');
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('type', "urn:onesis:error:{$code}");
    }

    /** @return array<string, mixed> a line that adds three people: one in March, two in June */
    private function line(int $position, int $opening = 2, int $target = 5, ?int $cost = 600_000_000): array
    {
        $months = array_fill(0, 12, 0);
        $months[2] = 1;
        $months[5] = $target - $opening - 1;

        return ['position_id' => $position, 'opening_headcount' => $opening, 'target_headcount' => $target, 'months' => $months, 'estimated_annual_cost' => $cost];
    }

    /** @return array<string, mixed> */
    private function plan(): array
    {
        return ['fiscal_year' => 2027, 'lines' => [$this->line($this->accountant), $this->line($this->clerk, 1, 3, 240_000_000)]];
    }

    private function createPlan(?UserModel $by = null): int
    {
        return (int) $this->as($by ?? $this->admin, 'POST', '/company/workforce/plans', $this->plan())->assertCreated()->json('data.id');
    }

    private function approvalFlow(): void
    {
        $this->as($this->admin, 'PUT', '/organization/approval-flows/workforce.plan', ['steps' => [
            ['mode' => 'any', 'approvers' => [['kind' => 'member', 'value' => (string) $this->reviewer->id]]],
        ]])->assertOk();
    }

    // ----- reading and writing -----

    public function test_a_plan_is_created_as_a_draft_with_its_lines_and_the_fiscal_year_days(): void
    {
        $response = $this->as($this->admin, 'POST', '/company/workforce/plans', $this->plan())->assertCreated();

        $response->assertJsonPath('data.status', 'draft')->assertJsonPath('data.version', 1)->assertJsonPath('data.fiscal_year', 2027)
            ->assertJsonPath('data.legal_entity_name', 'PT Holding')->assertJsonPath('data.starts_on', '2027-01-01')->assertJsonPath('data.ends_on', '2027-12-31')
            ->assertJsonPath('data.total_additions', 5)->assertJsonPath('data.estimated_cost', 840_000_000)->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.position_code', 'ACC')->assertJsonPath('data.lines.0.additions', 3)->assertJsonPath('data.lines.0.months.5', 2)
            ->assertJsonPath('data.lines.0.estimated_annual_cost', 600_000_000);

        $entry = DB::table('platform_audit_logs')->where('action', 'workforce.plan_created')->first();
        $this->assertSame($this->holding, (int) $entry->company_id);
        $this->assertSame($this->group, (int) $entry->group_id);
    }

    public function test_the_fiscal_year_follows_the_start_month_of_the_group(): void
    {
        $this->as($this->admin, 'PATCH', '/organization/settings', ['workforce.fiscal_year_start_month' => 4])->assertOk();

        $this->as($this->admin, 'POST', '/company/workforce/plans', $this->plan())->assertCreated()->assertJsonPath('data.starts_on', '2027-04-01')->assertJsonPath('data.ends_on', '2028-03-31');
    }

    public function test_the_lines_are_checked(): void
    {
        $body = fn (array $line): array => ['fiscal_year' => 2027, 'lines' => [$line]];
        $create = fn (array $line): TestResponse => $this->as($this->admin, 'POST', '/company/workforce/plans', $body($line));

        $this->error($create([...$this->line($this->accountant), 'target_headcount' => 6]), 422, 'invalid-plan-line');
        $this->error($create([...$this->line($this->accountant), 'target_headcount' => 1]), 422, 'invalid-plan-line');
        $this->error($create($this->line(999999)), 422, 'invalid-plan-line');
        $this->error($this->as($this->admin, 'POST', '/company/workforce/plans', ['fiscal_year' => 2027, 'lines' => [$this->line($this->accountant), $this->line($this->accountant)]]), 422, 'invalid-plan-line');
        $create([...$this->line($this->accountant), 'months' => [1, 2]])->assertStatus(422)->assertJsonValidationErrors(['lines.0.months']);
        $this->as($this->admin, 'POST', '/company/workforce/plans', ['fiscal_year' => 1900])->assertStatus(422)->assertJsonValidationErrors(['fiscal_year']);

        $this->as($this->admin, 'POST', "/organization/positions/{$this->clerk}/deactivate")->assertOk();
        $this->error($create($this->line($this->clerk)), 422, 'invalid-plan-line');
    }

    public function test_a_year_has_one_plan_until_it_is_revised(): void
    {
        $this->createPlan();

        $this->error($this->as($this->admin, 'POST', '/company/workforce/plans', $this->plan()), 409, 'plan-exists');
        $this->as($this->admin, 'POST', '/company/workforce/plans', [...$this->plan(), 'fiscal_year' => 2028])->assertCreated();
    }

    public function test_a_draft_is_changed_and_a_member_without_the_cost_permission_never_sees_or_changes_costs(): void
    {
        $id = $this->createPlan();

        $seen = $this->as($this->planner, 'GET', "/company/workforce/plans/{$id}")->assertOk();
        $this->assertArrayNotHasKey('estimated_cost', $seen->json('data'));
        $this->assertArrayNotHasKey('estimated_annual_cost', $seen->json('data.lines.0'));

        $changed = $this->plan();
        $changed['lines'] = [$this->line($this->accountant, 2, 4, 1)];
        $this->as($this->planner, 'PUT', "/company/workforce/plans/{$id}", ['lines' => $changed['lines']])->assertOk()->assertJsonCount(1, 'data.lines');
        $this->as($this->admin, 'GET', "/company/workforce/plans/{$id}")->assertJsonPath('data.lines.0.estimated_annual_cost', 600_000_000)->assertJsonPath('data.lines.0.target_headcount', 4);

        $this->as($this->admin, 'PUT', "/company/workforce/plans/{$id}", ['lines' => $changed['lines']])->assertOk()->assertJsonPath('data.lines.0.estimated_annual_cost', 1);
    }

    public function test_the_plan_permissions_decide_who_reads_and_who_writes(): void
    {
        $id = $this->createPlan();

        $this->as($this->viewer, 'GET', '/company/workforce/plans')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->error($this->as($this->viewer, 'POST', '/company/workforce/plans', [...$this->plan(), 'fiscal_year' => 2028]), 403, 'company-permission-required');
        $this->error($this->as($this->viewer, 'PUT', "/company/workforce/plans/{$id}", ['lines' => []]), 403, 'company-permission-required');
        $this->error($this->as($this->nobody, 'GET', '/company/workforce/plans'), 403, 'company-permission-required');
        $this->error($this->as($this->outsider, 'GET', '/company/workforce/plans'), 403, 'not-an-internal-tenant');
        $this->as($this->planner, 'GET', '/company/workforce/plans')->assertOk()->assertJsonCount(1, 'data');

        $this->as($this->admin, 'GET', '/company/workforce/plans?fiscal_year=2030')->assertOk()->assertJsonCount(0, 'data');
        $this->as($this->admin, 'GET', '/company/workforce/plans?status=approved')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_group_reads_every_legal_entity_and_only_a_group_manager_changes_plans(): void
    {
        $id = $this->createPlan();

        $this->as($this->groupReader, 'GET', '/organization/workforce/plans')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.estimated_cost', 840_000_000);
        $this->as($this->groupReader, 'GET', "/organization/workforce/plans/{$id}")->assertOk()->assertJsonCount(2, 'data.lines');
        $this->as($this->groupReader, 'GET', "/organization/workforce/plans?legal_entity_id={$this->entity}")->assertOk()->assertJsonCount(1, 'data');
        $this->as($this->groupReader, 'GET', '/organization/workforce/plans?legal_entity_id=999999')->assertOk()->assertJsonCount(0, 'data');
        $this->error($this->as($this->groupReader, 'POST', '/organization/workforce/plans', [...$this->plan(), 'fiscal_year' => 2028, 'legal_entity_id' => $this->entity]), 403, 'group-permission-required');
        $this->error($this->as($this->nobody, 'GET', '/organization/workforce/plans'), 403, 'not-a-group-member');

        $this->as($this->admin, 'POST', '/organization/workforce/plans', [...$this->plan(), 'fiscal_year' => 2028, 'legal_entity_id' => $this->entity])->assertCreated()->assertJsonPath('data.fiscal_year', 2028);
        $this->error($this->as($this->admin, 'POST', '/organization/workforce/plans', ['fiscal_year' => 2029]), 422, 'invalid-plan-line');
        $this->error($this->as($this->admin, 'POST', '/organization/workforce/plans', ['fiscal_year' => 2029, 'legal_entity_id' => 999999]), 404, 'not-found');
    }

    public function test_a_plan_of_another_group_does_not_exist_for_a_tenant(): void
    {
        $other = $this->operator('POST', 'groups', ['name' => 'Grup Lain', 'legal_entity' => ['legal_name' => 'PT Lain', 'legal_form' => 'pt']])->assertCreated();
        $otherEntity = (int) $other->json('data.legal_entities.0.id');
        $this->operator('PUT', "groups/{$other->json('data.id')}/legal-entities/{$otherEntity}/tenant", ['company_id' => $this->companyRowId('pt-luar')])->assertOk();
        $this->operator('PUT', "groups/{$other->json('data.id')}/members/{$this->outsider->id}", ['permissions' => ['group.workforce.manage']])->assertOk();

        $id = $this->createPlan();

        $this->error($this->as($this->outsider, 'GET', "/company/workforce/plans/{$id}"), 404, 'not-found');
        $this->error($this->as($this->outsider, 'GET', "/organization/workforce/plans/{$id}"), 404, 'not-found');
        $this->error($this->as($this->outsider, 'PUT', "/organization/workforce/plans/{$id}", ['lines' => []]), 404, 'not-found');
    }

    // ----- approval -----

    public function test_a_plan_without_lines_or_without_a_flow_is_not_submitted(): void
    {
        $empty = (int) $this->as($this->admin, 'POST', '/company/workforce/plans', ['fiscal_year' => 2028])->assertCreated()->json('data.id');
        $this->error($this->as($this->admin, 'POST', "/company/workforce/plans/{$empty}/submit"), 422, 'plan-empty');

        $id = $this->createPlan();
        $this->error($this->as($this->admin, 'POST', "/company/workforce/plans/{$id}/submit"), 409, 'approval-flow-missing');
        $this->as($this->admin, 'GET', "/company/workforce/plans/{$id}")->assertJsonPath('data.status', 'draft');
    }

    public function test_an_approved_plan_is_in_force_and_cannot_be_edited(): void
    {
        $this->approvalFlow();
        $id = $this->createPlan();

        $submitted = $this->as($this->planner, 'POST', "/company/workforce/plans/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'pending_approval');
        $this->assertNotNull($submitted->json('data.approval_id'));
        $this->error($this->as($this->planner, 'PUT', "/company/workforce/plans/{$id}", ['lines' => []]), 409, 'plan-not-editable');
        $this->error($this->as($this->planner, 'POST', "/company/workforce/plans/{$id}/submit"), 409, 'plan-not-editable');

        $this->as($this->reviewer, 'POST', "/company/approvals/{$submitted->json('data.approval_id')}/approve")->assertOk();

        $this->as($this->admin, 'GET', "/company/workforce/plans/{$id}")->assertJsonPath('data.status', 'approved');
        $this->error($this->as($this->planner, 'PUT', "/company/workforce/plans/{$id}", ['lines' => []]), 409, 'plan-not-editable');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'workforce.plan_approved')->count());
    }

    public function test_a_rejection_and_a_return_make_the_plan_editable_again(): void
    {
        $this->approvalFlow();
        $id = $this->createPlan();

        $approval = (int) $this->as($this->planner, 'POST', "/company/workforce/plans/{$id}/submit")->assertOk()->json('data.approval_id');
        $this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/reject", ['reason' => 'Too many hires in finance'])->assertOk();
        $this->as($this->admin, 'GET', "/company/workforce/plans/{$id}")->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.decision_reason', 'Too many hires in finance');

        $this->as($this->planner, 'PUT', "/company/workforce/plans/{$id}", ['lines' => [$this->line($this->accountant, 2, 3)]])->assertOk()->assertJsonPath('data.status', 'draft');

        $second = (int) $this->as($this->planner, 'POST', "/company/workforce/plans/{$id}/submit")->assertOk()->json('data.approval_id');
        $this->assertNotSame($approval, $second);
        $this->as($this->reviewer, 'POST', "/company/approvals/{$second}/return", ['reason' => 'Add the cost estimate'])->assertOk();
        $this->as($this->admin, 'GET', "/company/workforce/plans/{$id}")->assertJsonPath('data.status', 'draft')->assertJsonPath('data.decision_reason', 'Add the cost estimate');
    }

    public function test_a_revision_supersedes_the_version_in_force_once_it_is_approved(): void
    {
        $this->approvalFlow();
        $first = $this->createPlan();

        $this->error($this->as($this->admin, 'POST', "/company/workforce/plans/{$first}/revise"), 409, 'plan-not-in-force');

        $approval = (int) $this->as($this->admin, 'POST', "/company/workforce/plans/{$first}/submit")->assertOk()->json('data.approval_id');
        $this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/approve")->assertOk();

        $revision = $this->as($this->planner, 'POST', "/company/workforce/plans/{$first}/revise")->assertCreated()->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'draft')->assertJsonCount(2, 'data.lines');
        $second = (int) $revision->json('data.id');
        $this->error($this->as($this->planner, 'POST', "/company/workforce/plans/{$first}/revise"), 409, 'plan-revision-open');
        $this->as($this->admin, 'GET', "/company/workforce/plans/{$first}")->assertJsonPath('data.status', 'approved');

        $this->as($this->planner, 'PUT', "/company/workforce/plans/{$second}", ['lines' => [$this->line($this->accountant, 2, 7)]])->assertOk();
        $approval = (int) $this->as($this->planner, 'POST', "/company/workforce/plans/{$second}/submit")->assertOk()->json('data.approval_id');
        $this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/approve")->assertOk();

        $this->as($this->admin, 'GET', "/company/workforce/plans/{$first}")->assertJsonPath('data.status', 'superseded');
        $this->as($this->admin, 'GET', "/company/workforce/plans/{$second}")->assertJsonPath('data.status', 'approved');
        $this->as($this->admin, 'GET', '/company/workforce/plans?status=approved')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second);
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'workforce.plan_superseded')->count());
    }

    public function test_the_flow_of_a_plan_can_read_its_facts(): void
    {
        $this->as($this->admin, 'PUT', '/organization/approval-flows/workforce.plan', ['steps' => [
            ['mode' => 'any', 'approvers' => [['kind' => 'member', 'value' => (string) $this->reviewer->id]]],
            [
                'mode' => 'any', 'conditions' => [['fact' => 'additions', 'operator' => 'gte', 'value' => 10]],
                'approvers' => [['kind' => 'member', 'value' => (string) $this->groupReader->id]],
            ],
        ]])->assertOk();
        $id = $this->createPlan();

        $approval = (int) $this->as($this->admin, 'POST', "/company/workforce/plans/{$id}/submit")->assertOk()->json('data.approval_id');
        $this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/approve")->assertOk()->assertJsonCount(1, 'data.steps');
        $this->as($this->admin, 'GET', "/company/workforce/plans/{$id}")->assertJsonPath('data.status', 'approved');
    }

    // ----- the choices of the forms -----

    public function test_a_planner_reads_the_structure_of_their_own_legal_entity_for_the_forms(): void
    {
        $cost = (int) $this->as($this->admin, 'POST', '/organization/cost-centres', ['legal_entity_id' => $this->entity, 'code' => 'CC1', 'name' => 'Head office'])->assertCreated()->json('data.id');
        $this->as($this->admin, 'POST', '/organization/grades', ['code' => 'G1', 'name' => 'Staff', 'rank' => 1])->assertCreated();

        $response = $this->as($this->planner, 'GET', '/company/workforce/options')->assertOk();

        $this->assertSame($this->entity, $response->json('data.legal_entity_id'));
        $this->assertSame(['ACC', 'CLK'], array_column($response->json('data.positions'), 'code'));
        $this->assertSame(['Position ACC', 'Position CLK'], array_column($response->json('data.positions'), 'title'));
        $this->assertSame(['FIN'], array_column($response->json('data.units'), 'code'));
        $this->assertSame(['G1'], array_column($response->json('data.grades'), 'code'));
        $this->assertSame([$cost], array_column($response->json('data.cost_centres'), 'id'));
        $this->assertContains($this->planner->id, array_column($response->json('data.hiring_managers'), 'id'));
    }

    public function test_the_choices_are_closed_to_a_viewer_to_an_outsider_and_to_an_external_tenant(): void
    {
        $this->as($this->viewer, 'GET', '/company/workforce/options')->assertStatus(403);
        $this->as($this->nobody, 'GET', '/company/workforce/options')->assertStatus(403);
        $this->as($this->outsider, 'GET', '/company/workforce/options')->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-an-internal-tenant');
    }

    public function test_a_deactivated_position_is_not_offered(): void
    {
        $this->as($this->admin, 'POST', "/organization/positions/{$this->clerk}/deactivate")->assertOk();

        $this->assertSame(['ACC'], array_column($this->as($this->planner, 'GET', '/company/workforce/options')->assertOk()->json('data.positions'), 'code'));
    }
}
