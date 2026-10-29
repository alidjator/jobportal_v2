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

/** `docs/design/erp-requisitions.md` sections 1 and 3: requisitions, their quota in the plan, amendments, and the jobs opened for them. */
final class RequisitionTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private int $holding;

    private int $group;

    private int $entity;

    private UserModel $admin;

    private UserModel $requester;

    private UserModel $viewer;

    private UserModel $manager;

    private UserModel $reviewer;

    private UserModel $groupReader;

    private UserModel $nobody;

    private UserModel $outsider;

    private int $accountant;

    private int $senior;

    private int $juniorGrade;

    private int $seniorGrade;

    private int $contract;

    private int $permanent;

    private int $centre;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->holding = $this->companyRowId('pt-holding');
        $this->admin = $this->member($this->holding, 'andi@example.test', 'company_admin');
        $this->requester = $this->member($this->holding, 'rina@example.test', 'member', ['workforce.requisitions.create', 'workforce.plans.manage', 'workforce.costs.view', 'jobs.create', 'applications.view']);
        $this->viewer = $this->member($this->holding, 'vira@example.test', 'member', ['workforce.requisitions.view']);
        $this->manager = $this->member($this->holding, 'mira@example.test', 'member', []);
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

        $this->juniorGrade = (int) $this->as($this->admin, 'POST', '/organization/grades', ['code' => 'G1', 'name' => 'Junior', 'rank' => 1])->assertCreated()->json('data.id');
        $this->seniorGrade = (int) $this->as($this->admin, 'POST', '/organization/grades', ['code' => 'G3', 'name' => 'Senior', 'rank' => 3])->assertCreated()->json('data.id');
        $this->centre = (int) $this->as($this->admin, 'POST', '/organization/cost-centres', ['legal_entity_id' => $this->entity, 'code' => 'CC1', 'name' => 'Finance cost'])->assertCreated()->json('data.id');
        $type = (int) $this->as($this->admin, 'POST', '/organization/unit-types', ['code' => 'DEPT', 'name' => 'Department', 'level_order' => 2])->assertCreated()->json('data.id');
        $unit = (int) $this->as($this->admin, 'POST', '/organization/units', ['legal_entity_id' => $this->entity, 'code' => 'FIN', 'name' => 'Finance', 'unit_type_id' => $type, 'cost_centre_id' => $this->centre])->assertCreated()->json('data.id');
        $this->accountant = (int) $this->as($this->admin, 'POST', '/organization/positions', [
            'legal_entity_id' => $this->entity, 'code' => 'ACC', 'title' => 'Accountant', 'unit_id' => $unit, 'min_grade_id' => $this->juniorGrade, 'max_grade_id' => $this->juniorGrade,
        ])->assertCreated()->json('data.id');
        $this->senior = (int) $this->as($this->admin, 'POST', '/organization/positions', ['legal_entity_id' => $this->entity, 'code' => 'SAC', 'title' => 'Senior accountant', 'unit_id' => $unit])->assertCreated()->json('data.id');

        $this->contract = $this->referenceId('reference_job_types', 'contract');
        $this->permanent = $this->referenceId('reference_job_types', 'permanent');
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

    private function error(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('type', "urn:onesis:error:{$code}");
    }

    /**
     * @param  array<string, mixed>  $more
     * @return array<string, mixed>
     */
    private function body(array $more = []): array
    {
        return [
            'type' => 'unplanned', 'position_id' => $this->accountant, 'hires' => 2, 'job_type_id' => $this->permanent, 'target_join_date' => '2027-03-15',
            'justification' => 'The month-end close needs more hands.', 'hiring_manager_user_id' => $this->manager->id, ...$more,
        ];
    }

    private function draft(array $more = []): int
    {
        return (int) $this->as($this->requester, 'POST', '/company/requisitions', $this->body($more))->assertCreated()->json('data.id');
    }

    private function flows(): void
    {
        foreach (['workforce.plan', 'workforce.requisition', 'workforce.requisition_amendment'] as $subject) {
            $this->as($this->admin, 'PUT', "/organization/approval-flows/{$subject}", ['steps' => [
                ['mode' => 'any', 'approvers' => [['kind' => 'member', 'value' => (string) $this->reviewer->id]]],
            ]])->assertOk();
        }
    }

    /** Approves the plan of 2027 that gives the accountant three additions (two now, one in June). */
    private function approvedPlan(int $additions = 3): void
    {
        $this->flows();
        $months = array_fill(0, 12, 0);
        $months[5] = $additions;
        $id = (int) $this->as($this->requester, 'POST', '/company/workforce/plans', ['fiscal_year' => 2027, 'lines' => [
            ['position_id' => $this->accountant, 'opening_headcount' => 1, 'target_headcount' => 1 + $additions, 'months' => $months],
        ]])->assertCreated()->json('data.id');
        $approval = (int) $this->as($this->requester, 'POST', "/company/workforce/plans/{$id}/submit")->assertOk()->json('data.approval_id');
        $this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/approve")->assertOk();
    }

    /** Submits the requisition and has the reviewer approve it. */
    private function approve(int $id): void
    {
        $approval = (int) $this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit")->assertOk()->json('data.approval_id');
        $this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/approve")->assertOk();
    }

    // ----- writing -----

    public function test_a_requisition_is_a_draft_with_the_unit_cost_centre_and_the_fiscal_year_of_its_date(): void
    {
        $response = $this->as($this->requester, 'POST', '/company/requisitions', $this->body(['salary_min' => 8_000_000, 'salary_max' => 12_000_000]))->assertCreated();

        $response->assertJsonPath('data.status', 'draft')->assertJsonPath('data.type', 'unplanned')->assertJsonPath('data.position.code', 'ACC')->assertJsonPath('data.position.title', 'Accountant')
            ->assertJsonPath('data.cost_centre_id', $this->centre)->assertJsonPath('data.fiscal_year', 2027)->assertJsonPath('data.hires', 2)->assertJsonPath('data.requester_id', $this->requester->id)
            ->assertJsonPath('data.contract_months', null)->assertJsonPath('data.replacement', null)->assertJsonPath('data.salary_max', 12_000_000)->assertJsonPath('data.job_count', 0);

        $entry = DB::table('platform_audit_logs')->where('action', 'workforce.requisition_created')->first();
        $this->assertSame($this->holding, (int) $entry->company_id);
        $this->assertSame($this->group, (int) $entry->group_id);
    }

    public function test_the_fiscal_year_follows_the_start_month_of_the_group(): void
    {
        $this->as($this->admin, 'PATCH', '/organization/settings', ['workforce.fiscal_year_start_month' => 4])->assertOk();
        $this->as($this->requester, 'POST', '/company/requisitions', $this->body(['target_join_date' => '2027-03-15']))->assertCreated()->assertJsonPath('data.fiscal_year', 2026);
    }

    public function test_what_is_written_is_checked(): void
    {
        $bad = fn (array $more, string $code = 'invalid-requisition-field') => $this->error($this->as($this->requester, 'POST', '/company/requisitions', $this->body($more)), 422, $code);

        $bad(['position_id' => 999999]);
        $bad(['requested_grade_id' => $this->seniorGrade]);
        $bad(['requested_grade_id' => 999999]);
        $bad(['job_type_id' => $this->contract]);
        $bad(['job_type_id' => 999999]);
        $bad(['target_join_date' => '2026-10-15']);
        $bad(['salary_min' => 9, 'salary_max' => 1]);
        $bad(['cost_centre_id' => 999999]);
        $bad(['hiring_manager_user_id' => $this->outsider->id]);
        $bad(['type' => 'replacement']);
        $this->as($this->requester, 'POST', '/company/requisitions', $this->body(['hires' => 0, 'justification' => 'short']))->assertStatus(422)->assertJsonValidationErrors(['hires', 'justification']);

        $this->as($this->admin, 'POST', "/organization/positions/{$this->senior}/deactivate")->assertOk();
        $bad(['position_id' => $this->senior]);
    }

    public function test_a_contract_names_its_length_and_a_replacement_its_leaver(): void
    {
        $contract = $this->as($this->requester, 'POST', '/company/requisitions', $this->body(['job_type_id' => $this->contract, 'contract_months' => 12]))->assertCreated();
        $contract->assertJsonPath('data.contract_months', 12);

        $kept = $this->as($this->requester, 'POST', '/company/requisitions', $this->body(['contract_months' => 6, 'replacement_name' => 'Budi', 'replacement_leaving_date' => '2027-02-01']))->assertCreated();
        $kept->assertJsonPath('data.contract_months', null)->assertJsonPath('data.replacement', null);

        $this->as($this->requester, 'POST', '/company/requisitions', $this->body(['type' => 'replacement', 'hires' => 1, 'replacement_name' => 'Budi', 'replacement_leaving_date' => '2027-02-01']))
            ->assertCreated()->assertJsonPath('data.replacement.name', 'Budi')->assertJsonPath('data.replacement.leaving_date', '2027-02-01');
    }

    public function test_the_permissions_decide_who_reads_and_who_writes(): void
    {
        $id = $this->draft(['salary_max' => 5_000_000]);

        $seen = $this->as($this->viewer, 'GET', "/company/requisitions/{$id}")->assertOk();
        $this->assertArrayNotHasKey('salary_max', $seen->json('data'));
        $this->as($this->viewer, 'GET', '/company/requisitions')->assertOk()->assertJsonCount(1, 'data');
        $this->error($this->as($this->viewer, 'POST', '/company/requisitions', $this->body()), 403, 'company-permission-required');
        $this->error($this->as($this->viewer, 'POST', "/company/requisitions/{$id}/cancel"), 403, 'company-permission-required');
        $this->error($this->as($this->nobody, 'GET', '/company/requisitions'), 403, 'company-permission-required');
        $this->error($this->as($this->outsider, 'GET', '/company/requisitions'), 403, 'not-an-internal-tenant');

        $this->as($this->admin, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.salary_max', 5_000_000);
        $this->as($this->groupReader, 'GET', '/organization/requisitions')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.salary_max', 5_000_000);
        $this->as($this->groupReader, 'GET', "/organization/requisitions/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->as($this->groupReader, 'GET', '/organization/requisitions?status=approved')->assertOk()->assertJsonCount(0, 'data');
        $this->error($this->as($this->nobody, 'GET', '/organization/requisitions'), 403, 'not-a-group-member');
    }

    public function test_a_draft_is_changed_and_filtered(): void
    {
        $id = $this->draft();
        $this->draft(['type' => 'replacement', 'hires' => 1, 'replacement_name' => 'Budi', 'replacement_leaving_date' => '2027-02-01']);

        $this->as($this->requester, 'PUT', "/company/requisitions/{$id}", $this->body(['hires' => 4, 'position_id' => $this->senior]))->assertOk()->assertJsonPath('data.hires', 4)->assertJsonPath('data.position.code', 'SAC');
        $this->as($this->requester, 'GET', '/company/requisitions?type=replacement')->assertJsonCount(1, 'data');
        $this->as($this->requester, 'GET', "/company/requisitions?position_id={$this->senior}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->as($this->requester, 'GET', '/company/requisitions?status=draft')->assertJsonCount(2, 'data');
    }

    // ----- work location -----

    private function location(string $name = 'Pabrik Jawa Timur', string $status = 'active', ?int $companyId = null): int
    {
        return (int) DB::table('company_locations')->insertGetId([
            'company_id' => $companyId ?? $this->holding, 'name' => $name, 'type' => 'site', 'is_main' => false, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_requisition_may_name_an_active_work_location_of_its_tenant(): void
    {
        $location = $this->location();

        $id = (int) $this->as($this->requester, 'POST', '/company/requisitions', $this->body(['location_id' => $location]))->assertCreated()->assertJsonPath('data.location_id', $location)->json('data.id');
        $this->as($this->requester, 'POST', '/company/requisitions', $this->body())->assertCreated()->assertJsonPath('data.location_id', null);

        $listed = collect($this->as($this->requester, 'GET', '/company/requisitions?status=draft')->assertOk()->json('data'));
        $this->assertSame($location, $listed->firstWhere('id', $id)['location_id']);
        $this->assertCount(1, $listed->whereNull('location_id'));
    }

    public function test_a_location_that_is_not_an_active_location_of_the_tenant_is_refused(): void
    {
        $bad = fn (int $id) => $this->error($this->as($this->requester, 'POST', '/company/requisitions', $this->body(['location_id' => $id])), 422, 'invalid-location');

        $bad($this->location('Tutup', 'inactive'));
        $bad($this->location('Milik lain', 'active', $this->companyRowId('pt-luar')));
        $bad(999999);
        $this->as($this->requester, 'POST', '/company/requisitions', $this->body(['location_id' => 'x']))->assertStatus(422)->assertJsonValidationErrors(['location_id']);
    }

    public function test_the_location_changes_with_the_draft_and_blocks_the_submit_once_it_is_deactivated(): void
    {
        $first = $this->location('Satu');
        $second = $this->location('Dua');
        $id = $this->draft(['location_id' => $first]);

        $this->as($this->requester, 'PUT', "/company/requisitions/{$id}", $this->body(['location_id' => $second]))->assertOk()->assertJsonPath('data.location_id', $second);
        $this->as($this->requester, 'PUT', "/company/requisitions/{$id}", $this->body(['location_id' => $first]))->assertOk()->assertJsonPath('data.location_id', $first);

        $this->flows();
        DB::table('company_locations')->where('id', $first)->update(['status' => 'inactive', 'deactivated_at' => now()]);

        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit"), 422, 'invalid-location');
        // Without the location the requisition can still be submitted, and the inactive location still shows on what already points to it until it is changed.
        $this->as($this->requester, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.location_id', $first);
        $this->as($this->requester, 'PUT', "/company/requisitions/{$id}", $this->body())->assertOk()->assertJsonPath('data.location_id', null);
        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit")->assertOk();
    }

    public function test_a_location_that_a_requisition_refers_to_can_only_be_deactivated(): void
    {
        $location = $this->location();
        $this->draft(['location_id' => $location]);

        $this->error($this->as($this->admin, 'DELETE', "/company/locations/{$location}"), 409, 'location-in-use');
        $this->as($this->admin, 'POST', "/company/locations/{$location}/deactivate")->assertOk();
        $this->assertSame(1, DB::table('company_locations')->where('id', $location)->count());
    }

    // ----- quota and approval -----

    public function test_a_planned_requisition_needs_a_line_in_the_plan_in_force_and_fits_its_quota(): void
    {
        $this->flows();
        $first = $this->draft(['type' => 'planned']);
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$first}/submit"), 422, 'requisition-no-plan-line');

        $this->approvedPlan(3);
        $this->as($this->requester, 'GET', "/company/requisitions/{$first}")->assertJsonPath('data.quota.additions', 3)->assertJsonPath('data.quota.remaining', 3);

        $second = $this->draft(['type' => 'planned', 'hires' => 2]);
        $this->as($this->requester, 'POST', "/company/requisitions/{$first}/submit")->assertOk();
        $this->as($this->requester, 'POST', "/company/requisitions/{$second}/submit")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:requisition-quota-exceeded')->assertJsonPath('remaining', 1);

        $this->as($this->requester, 'POST', "/company/requisitions/{$first}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->as($this->requester, 'POST', "/company/requisitions/{$second}/submit")->assertOk()->assertJsonPath('data.status', 'pending_approval');
    }

    public function test_other_types_use_no_quota(): void
    {
        $this->flows();
        $this->approvedPlan(1);

        $unplanned = $this->draft(['hires' => 9]);
        $replacement = $this->draft(['type' => 'replacement', 'hires' => 1, 'replacement_name' => 'Budi', 'replacement_leaving_date' => '2027-02-01']);
        $this->as($this->requester, 'POST', "/company/requisitions/{$unplanned}/submit")->assertOk();
        $this->as($this->requester, 'POST', "/company/requisitions/{$replacement}/submit")->assertOk();
        $this->as($this->requester, 'GET', "/company/requisitions/{$unplanned}")->assertJsonPath('data.quota', null);
    }

    public function test_a_plan_line_cannot_drop_below_what_requisitions_use(): void
    {
        $this->approvedPlan(3);
        $planId = (int) DB::table('workforce_plans')->value('id');
        $id = $this->draft(['type' => 'planned', 'hires' => 2]);
        $this->approve($id);

        $revision = (int) $this->as($this->requester, 'POST', "/company/workforce/plans/{$planId}/revise")->assertCreated()->json('data.id');
        $months = array_fill(0, 12, 0);
        $months[5] = 1;
        $low = ['lines' => [['position_id' => $this->accountant, 'opening_headcount' => 1, 'target_headcount' => 2, 'months' => $months]]];
        $this->error($this->as($this->requester, 'PUT', "/company/workforce/plans/{$revision}", $low), 422, 'plan-below-requisitions');
        $this->error($this->as($this->requester, 'PUT', "/company/workforce/plans/{$revision}", ['lines' => []]), 422, 'plan-below-requisitions');

        $months[5] = 2;
        $this->as($this->requester, 'PUT', "/company/workforce/plans/{$revision}", ['lines' => [['position_id' => $this->accountant, 'opening_headcount' => 1, 'target_headcount' => 3, 'months' => $months]]])->assertOk();
    }

    public function test_a_flow_is_needed_and_an_approval_makes_the_requisition_usable(): void
    {
        $id = $this->draft();
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit"), 409, 'approval-flow-missing');

        $this->flows();
        $submitted = $this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'pending_approval');
        $this->error($this->as($this->requester, 'PUT', "/company/requisitions/{$id}", $this->body()), 409, 'requisition-not-editable');
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit"), 409, 'requisition-not-editable');

        $this->as($this->reviewer, 'POST', "/company/approvals/{$submitted->json('data.approval_id')}/approve")->assertOk();
        $this->as($this->requester, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.status', 'approved')->assertJsonPath('data.hires', 2);
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'workforce.requisition_approved')->count());
    }

    public function test_the_hiring_manager_can_be_the_approver(): void
    {
        $this->as($this->admin, 'PUT', '/organization/approval-flows/workforce.requisition', ['steps' => [
            ['mode' => 'any', 'approvers' => [['kind' => 'relative', 'value' => 'hiring_manager']]],
            ['mode' => 'any', 'conditions' => [['fact' => 'hires', 'operator' => 'gte', 'value' => 5]], 'approvers' => [['kind' => 'member', 'value' => (string) $this->reviewer->id]]],
        ]])->assertOk();
        $id = $this->draft(['hires' => 5]);

        $approval = (int) $this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit")->assertOk()->json('data.approval_id');
        $this->error($this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/approve"), 403, 'approval-decision-not-allowed');
        $this->as($this->manager, 'POST', "/company/approvals/{$approval}/approve")->assertOk();
        $this->as($this->requester, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.status', 'pending_approval');

        $this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/approve")->assertOk();
        $this->as($this->requester, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.status', 'approved');
    }

    public function test_a_rejection_and_a_return_make_the_requisition_editable_again(): void
    {
        $this->flows();
        $id = $this->draft();

        $approval = (int) $this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit")->assertOk()->json('data.approval_id');
        $this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/reject", ['reason' => 'No budget this quarter'])->assertOk();
        $this->as($this->requester, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.decision_reason', 'No budget this quarter');

        $this->as($this->requester, 'PUT', "/company/requisitions/{$id}", $this->body(['hires' => 1]))->assertOk()->assertJsonPath('data.status', 'draft');
        $approval = (int) $this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit")->assertOk()->json('data.approval_id');
        $this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/return", ['reason' => 'Add the salary range'])->assertOk();
        $this->as($this->requester, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.status', 'draft')->assertJsonPath('data.decision_reason', 'Add the salary range');
    }

    // ----- cancel, close, amend -----

    public function test_a_requisition_is_cancelled_before_a_job_exists_and_closed_after(): void
    {
        $this->flows();
        $id = $this->draft();
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/close", ['reason' => 'No longer needed']), 409, 'requisition-not-open');
        $this->approve($id);

        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/jobs")->assertCreated();
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/cancel"), 409, 'requisition-has-jobs');
        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/close", ['reason' => 'Hiring paused'])->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.closed_reason', 'Hiring paused');
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/cancel"), 409, 'requisition-not-cancellable');
        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/close", [])->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $other = $this->draft();
        $this->as($this->requester, 'POST', "/company/requisitions/{$other}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_a_pending_requisition_is_cancelled_with_its_approval(): void
    {
        $this->flows();
        $id = $this->draft();
        $approval = (int) $this->as($this->requester, 'POST', "/company/requisitions/{$id}/submit")->assertOk()->json('data.approval_id');

        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->error($this->as($this->reviewer, 'POST', "/company/approvals/{$approval}/approve"), 409, 'approval-not-pending');
    }

    public function test_lowering_the_hires_applies_at_once_and_raising_them_needs_approval(): void
    {
        $this->flows();
        $id = $this->draft(['hires' => 4, 'salary_max' => 10_000_000]);
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['hires' => 2, 'reason' => 'Fewer needed']), 409, 'requisition-not-open');
        $this->approve($id);

        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['reason' => 'Nothing to say']), 422, 'requisition-no-change');
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['hires' => 4, 'reason' => 'Nothing really']), 422, 'requisition-no-change');
        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['hires' => 3, 'salary_max' => 9_000_000, 'reason' => 'One position was filled inside'])->assertOk()
            ->assertJsonPath('data.hires', 3)->assertJsonPath('data.salary_max', 9_000_000)->assertJsonCount(0, 'data.amendments');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'workforce.requisition_reduced')->count());

        $response = $this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['hires' => 5, 'reason' => 'Two more for the audit season'])->assertOk()
            ->assertJsonPath('data.hires', 3)->assertJsonPath('data.amendments.0.status', 'pending_approval')->assertJsonPath('data.amendments.0.hires', 5);
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['hires' => 6, 'reason' => 'Even more people']), 409, 'amendment-pending');

        $this->as($this->reviewer, 'POST', '/company/approvals/'.$response->json('data.amendments.0.approval_id').'/approve')->assertOk();
        $this->as($this->requester, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.hires', 5)->assertJsonPath('data.amendments.0.status', 'approved')->assertJsonPath('data.salary_max', 9_000_000);
    }

    public function test_a_raise_of_the_salary_budget_and_a_rejected_amendment(): void
    {
        $this->flows();
        $id = $this->draft(['salary_max' => 10_000_000]);
        $this->approve($id);

        $amended = $this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['salary_max' => 12_000_000, 'reason' => 'The market moved'])->assertOk()->assertJsonPath('data.amendments.0.status', 'pending_approval');
        $this->as($this->reviewer, 'POST', '/company/approvals/'.$amended->json('data.amendments.0.approval_id').'/reject', ['reason' => 'Stay inside the budget'])->assertOk();
        $this->as($this->requester, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.salary_max', 10_000_000)->assertJsonPath('data.amendments.0.status', 'rejected')
            ->assertJsonPath('data.amendments.0.decision_reason', 'Stay inside the budget');

        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['salary_max' => 11_000_000, 'reason' => 'A smaller raise'])->assertOk()->assertJsonCount(2, 'data.amendments');
    }

    public function test_an_amendment_of_a_planned_requisition_fits_the_quota_and_closing_withdraws_it(): void
    {
        $this->approvedPlan(3);
        $id = $this->draft(['type' => 'planned', 'hires' => 2]);
        $this->approve($id);

        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['hires' => 4, 'reason' => 'A bit more than planned']), 422, 'requisition-quota-exceeded');
        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['hires' => 3, 'reason' => 'The last planned seat'])->assertOk()->assertJsonPath('data.amendments.0.status', 'pending_approval');

        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/close", ['reason' => 'Hiring paused'])->assertOk()->assertJsonPath('data.amendments.0.status', 'withdrawn');
    }

    // ----- jobs -----

    public function test_a_tenant_opens_its_jobs_from_a_requisition_and_not_otherwise(): void
    {
        $this->flows();
        $this->error($this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant']), 422, 'requisition-required');
        $draft = $this->draft(['hires' => 3]);
        $this->error($this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant', 'requisition_id' => $draft, 'vacancies' => 1]), 409, 'requisition-not-open');
        $this->approve($draft);

        $this->error($this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant', 'requisition_id' => $draft]), 422, 'invalid-job');
        $this->error($this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant', 'requisition_id' => 999999, 'vacancies' => 1]), 422, 'invalid-requisition');
        $this->error($this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant', 'requisition_id' => $draft, 'vacancies' => 4]), 422, 'requisition-vacancies-exceeded');

        $job = $this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant', 'requisition_id' => $draft, 'vacancies' => 1])->assertCreated()->assertJsonPath('data.requisition_id', $draft);
        $this->assertSame('in_recruitment', DB::table('workforce_requisitions')->where('id', $draft)->value('status'));

        $this->error($this->as($this->requester, 'POST', "/company/jobs/{$job->json('data.id')}/clone"), 422, 'requisition-required');
        $this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant', 'vacancies' => 1])->assertStatus(422);
    }

    public function test_the_form_of_a_new_job_lists_the_requisitions_still_open(): void
    {
        $this->flows();
        $none = $this->as($this->requester, 'GET', '/company/jobs/requisition-options')->assertOk()->assertJsonPath('data.required', true);
        $this->assertSame([], $none->json('data.requisitions'));

        $draft = $this->draft(['hires' => 3]);
        $this->assertSame([], $this->as($this->requester, 'GET', '/company/jobs/requisition-options')->json('data.requisitions'), 'a draft is not open');
        $this->approve($draft);

        $this->as($this->requester, 'GET', '/company/jobs/requisition-options')->assertOk()
            ->assertJsonPath('data.requisitions.0.id', $draft)->assertJsonPath('data.requisitions.0.hires', 3)->assertJsonPath('data.requisitions.0.remaining', 3)
            ->assertJsonPath('data.requisitions.0.position_title', fn ($title): bool => is_string($title) && $title !== '');

        $this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant', 'requisition_id' => $draft, 'vacancies' => 2])->assertCreated();
        $this->as($this->requester, 'GET', '/company/jobs/requisition-options')->assertJsonPath('data.requisitions.0.remaining', 1);

        $this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant', 'requisition_id' => $draft, 'vacancies' => 1])->assertCreated();
        $this->assertSame([], $this->as($this->requester, 'GET', '/company/jobs/requisition-options')->json('data.requisitions'), 'a requisition with no hires left is not offered');
    }

    public function test_the_jobs_of_a_requisition_stay_inside_its_hires(): void
    {
        $this->flows();
        $id = $this->draft(['hires' => 3]);
        $this->approve($id);

        $first = $this->as($this->requester, 'POST', "/company/requisitions/{$id}/jobs", ['vacancies' => 2])->assertCreated()->assertJsonPath('data.requisition_id', $id);
        $job = (int) $first->json('data.job_id');
        $this->as($this->requester, 'GET', "/company/jobs/{$job}")->assertOk()->assertJsonPath('data.title', 'Accountant')->assertJsonPath('data.vacancies', 2)->assertJsonPath('data.requisition_id', $id);

        $this->as($this->requester, 'GET', "/company/requisitions/{$id}")->assertJsonPath('data.status', 'in_recruitment')->assertJsonPath('data.job_count', 1)->assertJsonPath('data.open_vacancies', 2);
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/jobs", ['vacancies' => 2]), 422, 'requisition-vacancies-exceeded');

        $second = (int) $this->as($this->requester, 'POST', "/company/requisitions/{$id}/jobs", ['title' => 'Accounting staff'])->assertCreated()->json('data.job_id');
        $this->as($this->requester, 'GET', "/company/jobs/{$second}")->assertJsonPath('data.title', 'Accounting staff')->assertJsonPath('data.vacancies', 1);
        $this->error($this->as($this->requester, 'POST', "/company/requisitions/{$id}/jobs"), 422, 'requisition-vacancies-exceeded');

        $this->error($this->as($this->requester, 'PATCH', "/company/jobs/{$job}", ['vacancies' => 3]), 422, 'requisition-vacancies-exceeded');
        $this->as($this->requester, 'PATCH', "/company/jobs/{$job}", ['vacancies' => 1])->assertOk();
        $this->as($this->requester, 'POST', "/company/requisitions/{$id}/amend", ['hires' => 1, 'reason' => 'Cut to one only'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:requisition-hires-below-jobs');
    }

    public function test_the_job_rule_can_be_switched_off_for_a_company_and_does_not_touch_external_tenants(): void
    {
        DB::table('platform_setting_overrides')->insert([
            'key' => 'recruitment.requisition_required', 'scope_type' => 'company', 'scope_id' => $this->holding, 'value' => json_encode(false), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->as($this->requester, 'POST', '/company/jobs', ['title' => 'Accountant'])->assertCreated()->assertJsonPath('data.requisition_id', null);
        $id = (int) $this->as($this->admin, 'POST', '/company/jobs', ['title' => 'Auditor'])->assertCreated()->json('data.id');
        $this->as($this->admin, 'POST', "/company/jobs/{$id}/clone")->assertCreated();

        $this->as($this->outsider, 'POST', '/company/jobs', ['title' => 'Driver'])->assertCreated();
    }

    public function test_the_job_needs_the_permission_to_create_jobs_and_a_requisition_of_the_tenant(): void
    {
        $this->flows();
        $id = $this->draft();
        $this->approve($id);

        $this->error($this->as($this->viewer, 'POST', "/company/requisitions/{$id}/jobs"), 403, 'company-permission-required');
        $this->error($this->as($this->outsider, 'POST', "/company/requisitions/{$id}/jobs"), 403, 'not-an-internal-tenant');
        $this->error($this->as($this->requester, 'POST', '/company/requisitions/999999/jobs'), 404, 'not-found');
    }
}
