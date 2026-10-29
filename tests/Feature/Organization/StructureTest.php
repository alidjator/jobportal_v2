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

/** `docs/design/erp-organization.md` sections 5 and 6: grades, unit types, cost centres, and units and positions with dated versions and `as_of` reads. */
final class StructureTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private ?string $adminToken = null;

    private int $group;

    private int $entity;

    private int $holdingAdmin;

    private int $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');

        $holding = $this->companyRowId('pt-holding');
        $this->holdingAdmin = $this->member($holding, 'hana@example.test', 'company_admin');
        $this->viewer = $this->member($holding, 'vera@example.test', 'recruiter');

        $created = $this->admin('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding Nusantara', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $this->entity = (int) $created->json('data.legal_entities.0.id');
        $this->admin('PUT', "groups/{$this->group}/legal-entities/{$this->entity}/tenant", ['company_id' => $holding])->assertOk();
        $this->admin('PUT', "groups/{$this->group}/members/{$this->holdingAdmin}", ['permissions' => ['group.structure.manage', 'group.members.manage']])->assertOk();
        $this->admin('PUT', "groups/{$this->group}/members/{$this->viewer}", ['permissions' => ['group.recruitment.view']])->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ----- helpers -----

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
    private function as(int $userId, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $token = UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        return $this->withToken($token)->json($method, "/api/v1/organization/{$uri}", $body);
    }

    /** @param  array<string, mixed>  $body */
    private function manage(string $method, string $uri, array $body = []): TestResponse
    {
        return $this->as($this->holdingAdmin, $method, $uri, $body);
    }

    private function unitType(string $code = 'DEPT'): int
    {
        return (int) $this->manage('POST', 'unit-types', ['code' => $code, 'name' => ucfirst(strtolower($code)), 'level_order' => 2])->assertCreated()->json('data.id');
    }

    private function grade(string $code, int $rank): int
    {
        return (int) $this->manage('POST', 'grades', ['code' => $code, 'name' => "Grade {$code}", 'rank' => $rank])->assertCreated()->json('data.id');
    }

    /** @param  array<string, mixed>  $more */
    private function unit(string $code, int $type, array $more = []): int
    {
        return (int) $this->manage('POST', 'units', ['legal_entity_id' => $this->entity, 'code' => $code, 'name' => "Unit {$code}", 'unit_type_id' => $type, ...$more])->assertCreated()->json('data.id');
    }

    /** @param  array<string, mixed>  $more */
    private function position(string $code, int $unit, array $more = []): int
    {
        return (int) $this->manage('POST', 'positions', ['legal_entity_id' => $this->entity, 'code' => $code, 'title' => "Position {$code}", 'unit_id' => $unit, ...$more])->assertCreated()->json('data.id');
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('type', "urn:onesis:error:{$code}");
    }

    // ----- access -----

    public function test_any_group_member_reads_the_structure_but_only_structure_managers_change_it(): void
    {
        $type = $this->unitType();
        $this->grade('G1', 1);

        $this->as($this->viewer, 'GET', 'grades')->assertOk()->assertJsonPath('data.0.code', 'G1');
        $this->as($this->viewer, 'GET', 'unit-types')->assertOk()->assertJsonPath('data.0.id', $type);
        $this->as($this->viewer, 'GET', "units?legal_entity_id={$this->entity}")->assertOk();
        $this->error($this->as($this->viewer, 'POST', 'grades', ['code' => 'G2', 'name' => 'Grade two', 'rank' => 2]), 403, 'group-permission-required');

        $stranger = $this->member($this->companyRowId('pt-lain'), 'lain@example.test', 'company_admin');
        $this->error($this->as($stranger, 'GET', 'grades'), 403, 'not-a-group-member');
    }

    public function test_another_group_cannot_see_the_structure(): void
    {
        $unit = $this->unit('U1', $this->unitType());

        $other = $this->admin('POST', 'groups', ['name' => 'Grup Lain', 'legal_entity' => ['legal_name' => 'PT Lain', 'legal_form' => 'pt']])->assertCreated();
        $company = $this->companyRowId('pt-lain');
        $user = $this->member($company, 'lain@example.test', 'company_admin');
        $otherEntity = (int) $other->json('data.legal_entities.0.id');
        $this->admin('PUT', "groups/{$other->json('data.id')}/legal-entities/{$otherEntity}/tenant", ['company_id' => $company])->assertOk();
        $this->admin('PUT', "groups/{$other->json('data.id')}/members/{$user}", ['permissions' => ['group.structure.manage']])->assertOk();

        $this->as($user, 'GET', 'grades')->assertOk()->assertJsonCount(0, 'data');
        $this->as($user, 'GET', "units/{$unit}")->assertNotFound();
        $this->as($user, 'GET', "units?legal_entity_id={$this->entity}")->assertNotFound();
        $this->as($user, 'POST', 'cost-centres', ['legal_entity_id' => $this->entity, 'code' => 'CC1', 'name' => 'Not mine'])->assertNotFound();
    }

    // ----- grades, unit types, cost centres -----

    public function test_grades_are_created_edited_listed_by_rank_and_have_unique_codes(): void
    {
        $this->grade('G2', 2);
        $first = $this->grade('G1', 1);

        $this->manage('GET', 'grades')->assertOk()->assertJsonPath('data.0.code', 'G1')->assertJsonPath('data.1.code', 'G2');
        $this->error($this->manage('POST', 'grades', ['code' => 'g1', 'name' => 'Again', 'rank' => 5]), 409, 'grade-code-taken');
        $this->manage('POST', 'grades', ['code' => 'bad code', 'name' => 'X', 'rank' => 0])->assertStatus(422)->assertJsonValidationErrors(['code', 'name', 'rank']);

        $this->manage('PATCH', "grades/{$first}", ['name' => 'Staff', 'rank' => 3])->assertOk()->assertJsonPath('data.name', 'Staff')->assertJsonPath('data.rank', 3)->assertJsonPath('data.code', 'G1');
        $this->manage('PATCH', "grades/{$first}", ['code' => 'NEW'])->assertStatus(422)->assertJsonValidationErrors(['code']);

        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'group.grade_updated')->count());
        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'group.grade_created')->count());
    }

    public function test_unit_types_and_cost_centres_follow_the_same_rules(): void
    {
        $type = $this->unitType('DIV');
        $this->error($this->manage('POST', 'unit-types', ['code' => 'div', 'name' => 'Again', 'level_order' => 1]), 409, 'unit-type-code-taken');
        $this->manage('PATCH', "unit-types/{$type}", ['level_order' => 1])->assertOk()->assertJsonPath('data.level_order', 1);

        $centre = (int) $this->manage('POST', 'cost-centres', ['legal_entity_id' => $this->entity, 'code' => 'CC1', 'name' => 'Head office'])->assertCreated()->json('data.id');
        $this->error($this->manage('POST', 'cost-centres', ['legal_entity_id' => $this->entity, 'code' => 'CC1', 'name' => 'Again']), 409, 'cost-centre-code-taken');
        $this->manage('PATCH', "cost-centres/{$centre}", ['name' => 'HQ'])->assertOk()->assertJsonPath('data.name', 'HQ');
        $this->manage('GET', "cost-centres?legal_entity_id={$this->entity}")->assertOk()->assertJsonPath('data.0.code', 'CC1');
        $this->manage('GET', 'cost-centres')->assertStatus(422)->assertJsonValidationErrors(['legal_entity_id']);
    }

    public function test_an_item_in_use_is_not_deactivated_and_comes_back_on_reactivation(): void
    {
        $type = $this->unitType();
        $centre = (int) $this->manage('POST', 'cost-centres', ['legal_entity_id' => $this->entity, 'code' => 'CC1', 'name' => 'HQ'])->assertCreated()->json('data.id');
        $grade = $this->grade('G1', 1);
        $unit = $this->unit('U1', $type, ['cost_centre_id' => $centre]);
        $this->position('P1', $unit, ['min_grade_id' => $grade]);

        $this->error($this->manage('POST', "unit-types/{$type}/deactivate"), 409, 'unit-type-in-use');
        $this->error($this->manage('POST', "cost-centres/{$centre}/deactivate"), 409, 'cost-centre-in-use');
        $this->error($this->manage('POST', "grades/{$grade}/deactivate"), 409, 'grade-in-use');
        $this->error($this->manage('POST', "units/{$unit}/deactivate"), 409, 'unit-in-use');

        $spare = $this->grade('G9', 9);
        $this->manage('POST', "grades/{$spare}/deactivate")->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->error($this->manage('POST', "grades/{$spare}/deactivate"), 409, 'already-inactive');
        $this->manage('POST', "grades/{$spare}/reactivate")->assertOk()->assertJsonPath('data.status', 'active');
        $this->error($this->manage('POST', "grades/{$spare}/reactivate"), 409, 'already-active');
    }

    // ----- units -----

    public function test_a_unit_is_created_with_its_first_version_and_a_child_points_at_it(): void
    {
        $type = $this->unitType();
        $centre = (int) $this->manage('POST', 'cost-centres', ['legal_entity_id' => $this->entity, 'code' => 'CC1', 'name' => 'HQ'])->assertCreated()->json('data.id');
        $top = $this->unit('DIR', $type);
        $child = $this->manage('POST', 'units', ['legal_entity_id' => $this->entity, 'code' => 'FIN', 'name' => 'Finance', 'unit_type_id' => $type, 'parent_unit_id' => $top, 'cost_centre_id' => $centre])
            ->assertCreated()
            ->assertJsonPath('data.parent_unit_id', $top)
            ->assertJsonPath('data.cost_centre_id', $centre)
            ->assertJsonPath('data.valid_from', '2026-10-16')
            ->assertJsonPath('data.valid_to', null);

        $this->manage('GET', "units?legal_entity_id={$this->entity}")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.code', 'DIR')->assertJsonPath('data.1.code', 'FIN');
        $this->manage('GET', 'units/'.$child->json('data.id'))->assertOk()->assertJsonPath('data.name', 'Finance');
        $this->error($this->manage('POST', 'units', ['legal_entity_id' => $this->entity, 'code' => 'dir', 'name' => 'Again', 'unit_type_id' => $type]), 409, 'unit-code-taken');
    }

    public function test_a_change_is_a_new_version_and_as_of_reads_the_structure_on_any_day(): void
    {
        $type = $this->unitType();
        $unit = $this->unit('FIN', $type);

        $this->manage('PATCH', "units/{$unit}", ['name' => 'Finance and Tax', 'valid_from' => '2026-12-01'])->assertOk()->assertJsonPath('data.name', 'Unit FIN');

        $this->manage('GET', "units/{$unit}?as_of=2026-11-30")->assertOk()->assertJsonPath('data.name', 'Unit FIN')->assertJsonPath('data.valid_to', '2026-11-30');
        $this->manage('GET', "units/{$unit}?as_of=2026-12-01")->assertOk()->assertJsonPath('data.name', 'Finance and Tax')->assertJsonPath('data.valid_from', '2026-12-01')->assertJsonPath('data.valid_to', null);
        $this->manage('GET', "units?legal_entity_id={$this->entity}&as_of=2027-01-01")->assertOk()->assertJsonPath('data.0.name', 'Finance and Tax');
        $this->manage('GET', "units/{$unit}/versions")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.valid_to', '2026-11-30')->assertJsonPath('data.1.valid_from', '2026-12-01');

        $this->manage('GET', "units/{$unit}?as_of=2026-01-01")->assertNotFound();
        $this->manage('GET', "units?legal_entity_id={$this->entity}&as_of=2026-01-01")->assertOk()->assertJsonCount(0, 'data');
        $this->manage('GET', "units/{$unit}?as_of=not-a-day")->assertStatus(422)->assertJsonValidationErrors(['as_of']);
    }

    public function test_a_change_in_between_keeps_the_later_version_and_a_change_on_the_same_day_replaces_it(): void
    {
        $unit = $this->unit('FIN', $this->unitType());
        $this->manage('PATCH', "units/{$unit}", ['name' => 'December', 'valid_from' => '2026-12-01'])->assertOk();
        $this->manage('PATCH', "units/{$unit}", ['name' => 'November', 'valid_from' => '2026-11-01'])->assertOk();
        $this->manage('PATCH', "units/{$unit}", ['name' => 'December again', 'valid_from' => '2026-12-01'])->assertOk();

        $versions = $this->manage('GET', "units/{$unit}/versions")->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(['Unit FIN', 'November', 'December again'], array_column($versions->json('data'), 'name'));
        $this->assertSame(['2026-10-31', '2026-11-30', null], array_column($versions->json('data'), 'valid_to'));
    }

    public function test_a_change_dated_in_the_past_needs_a_reason_and_the_audit_keeps_both_versions(): void
    {
        $unit = $this->unit('FIN', $this->unitType(), ['valid_from' => '2026-01-01', 'reason' => 'Imported from the old organisation chart']);

        $this->error($this->manage('PATCH', "units/{$unit}", ['name' => 'Renamed', 'valid_from' => '2026-06-01']), 422, 'reason-required');
        $this->error($this->manage('PATCH', "units/{$unit}", ['name' => 'Renamed', 'valid_from' => '2026-06-01', 'reason' => 'abc']), 422, 'validation-failed');
        $this->error($this->manage('PATCH', "units/{$unit}", ['name' => 'Renamed', 'valid_from' => '2025-06-01', 'reason' => 'Before it existed']), 422, 'before-first-version');
        $this->manage('PATCH', "units/{$unit}", ['name' => 'Renamed', 'valid_from' => '2026-06-01', 'reason' => 'Corrects the June reorganisation'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->error($this->manage('PATCH', "units/{$unit}", []), 422, 'no-changes');

        $entry = DB::table('platform_audit_logs')->where('action', 'group.unit_restructured')->first();
        $metadata = json_decode((string) $entry->metadata, true);
        $this->assertSame('Corrects the June reorganisation', $metadata['reason']);
        $this->assertSame('2026-06-01', $metadata['valid_from']);
        $this->assertSame('Unit FIN', $metadata['before']['name']);
        $this->assertSame('Renamed', $metadata['after']['name']);
    }

    public function test_a_parent_must_be_valid_active_in_the_same_entity_and_never_a_descendant(): void
    {
        $type = $this->unitType();
        $top = $this->unit('DIR', $type);
        $child = $this->unit('FIN', $type, ['parent_unit_id' => $top]);
        $grandchild = $this->unit('TAX', $type, ['parent_unit_id' => $child]);

        $this->error($this->manage('PATCH', "units/{$top}", ['parent_unit_id' => $grandchild]), 422, 'parent-cycle');
        $this->error($this->manage('PATCH', "units/{$top}", ['parent_unit_id' => $top]), 422, 'invalid-parent');
        $this->error($this->manage('PATCH', "units/{$child}", ['parent_unit_id' => 999999]), 422, 'invalid-parent');
        $this->error($this->manage('PATCH', "units/{$child}", ['unit_type_id' => 999999]), 422, 'invalid-unit-type');
        $this->error($this->manage('PATCH', "units/{$child}", ['cost_centre_id' => 999999]), 422, 'invalid-cost-centre');

        $late = $this->unit('NEW', $type, ['valid_from' => '2026-12-01']);
        $this->error($this->manage('PATCH', "units/{$child}", ['parent_unit_id' => $late]), 422, 'invalid-parent');
        $this->manage('PATCH', "units/{$child}", ['parent_unit_id' => $late, 'valid_from' => '2026-12-01'])->assertOk();
        $this->manage('PATCH', "units/{$child}", ['parent_unit_id' => null])->assertOk()->assertJsonPath('data.parent_unit_id', null);
    }

    public function test_a_loop_that_appears_only_after_a_later_restructuring_is_refused(): void
    {
        $type = $this->unitType();
        $a = $this->unit('A', $type);
        $b = $this->unit('B', $type, ['parent_unit_id' => $a]);

        $this->manage('PATCH', "units/{$b}", ['parent_unit_id' => null, 'valid_from' => '2026-12-01'])->assertOk();
        $this->manage('PATCH', "units/{$a}", ['parent_unit_id' => $b, 'valid_from' => '2026-12-01'])->assertOk();
        $this->error($this->manage('PATCH', "units/{$a}", ['parent_unit_id' => $b, 'valid_from' => '2026-11-01']), 422, 'parent-cycle');
    }

    public function test_units_are_put_in_order_and_the_units_not_named_keep_their_places(): void
    {
        $type = $this->unitType();
        [$a, $b, $c, $d] = [$this->unit('AAA', $type), $this->unit('BBB', $type), $this->unit('CCC', $type), $this->unit('DDD', $type)];
        $codes = fn (): array => $this->manage('GET', "units?legal_entity_id={$this->entity}")->assertOk()->json('data.*.code');

        $this->assertSame(['AAA', 'BBB', 'CCC', 'DDD'], $codes());

        $this->manage('PUT', 'units/order', ['legal_entity_id' => $this->entity, 'unit_ids' => [$d, $b]])->assertNoContent();
        $this->assertSame(['AAA', 'DDD', 'CCC', 'BBB'], $codes());

        $this->manage('PUT', 'units/order', ['legal_entity_id' => $this->entity, 'unit_ids' => [$c, $d, $a]])->assertNoContent();
        $this->assertSame(['CCC', 'DDD', 'AAA', 'BBB'], $codes());

        $this->error($this->manage('PUT', 'units/order', ['legal_entity_id' => $this->entity, 'unit_ids' => [$a, 999999]]), 422, 'invalid-unit-order');
        $this->as($this->viewer, 'PUT', 'units/order', ['legal_entity_id' => $this->entity, 'unit_ids' => [$a, $b]])->assertForbidden();
        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'group.units_reordered')->count());
    }

    public function test_a_unit_is_deactivated_once_nothing_depends_on_it_and_a_deactivated_unit_is_not_a_parent(): void
    {
        $type = $this->unitType();
        $top = $this->unit('DIR', $type);
        $child = $this->unit('FIN', $type, ['parent_unit_id' => $top]);

        $this->error($this->manage('POST', "units/{$top}/deactivate"), 409, 'unit-in-use');
        $this->manage('POST', "units/{$child}/deactivate")->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->error($this->manage('PATCH', "units/{$child}", ['name' => 'Nope']), 409, 'unit-inactive');
        $this->manage('POST', "units/{$top}/deactivate")->assertOk();
        $this->error($this->manage('POST', 'units', ['legal_entity_id' => $this->entity, 'code' => 'X', 'name' => 'Under inactive', 'unit_type_id' => $type, 'parent_unit_id' => $top]), 422, 'invalid-parent');
        $this->manage('POST', "units/{$top}/reactivate")->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_nothing_is_added_to_an_inactive_legal_entity(): void
    {
        $type = $this->unitType();
        DB::table('company_companies')->update(['status' => 'suspended']);
        $this->manage('POST', "legal-entities/{$this->entity}/deactivate")->assertOk();

        $this->error($this->manage('POST', 'units', ['legal_entity_id' => $this->entity, 'code' => 'X', 'name' => 'New', 'unit_type_id' => $type]), 409, 'legal-entity-inactive');
        $this->error($this->manage('POST', 'cost-centres', ['legal_entity_id' => $this->entity, 'code' => 'X', 'name' => 'New']), 409, 'legal-entity-inactive');
    }

    // ----- positions -----

    public function test_a_position_is_created_with_a_grade_range_a_boss_and_a_job_role(): void
    {
        $unit = $this->unit('FIN', $this->unitType());
        $low = $this->grade('G1', 1);
        $high = $this->grade('G3', 3);
        $role = $this->referenceId('reference_job_roles', 'accountant');
        $boss = $this->position('HEAD', $unit);

        $this->manage('POST', 'positions', [
            'legal_entity_id' => $this->entity, 'code' => 'ACC', 'title' => 'Accountant', 'unit_id' => $unit, 'min_grade_id' => $low, 'max_grade_id' => $high,
            'reports_to_position_id' => $boss, 'job_role_id' => $role,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Accountant')
            ->assertJsonPath('data.min_grade_id', $low)
            ->assertJsonPath('data.reports_to_position_id', $boss)
            ->assertJsonPath('data.job_role_id', $role)
            ->assertJsonPath('data.valid_from', '2026-10-16');

        $this->manage('GET', "positions?legal_entity_id={$this->entity}")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.code', 'ACC');
        $this->error($this->manage('POST', 'positions', ['legal_entity_id' => $this->entity, 'code' => 'acc', 'title' => 'Again', 'unit_id' => $unit]), 409, 'position-code-taken');
        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'group.position_created')->count());
    }

    public function test_the_references_of_a_position_are_checked(): void
    {
        $unit = $this->unit('FIN', $this->unitType());
        $low = $this->grade('G1', 1);
        $high = $this->grade('G3', 3);
        $position = $this->position('P1', $unit);

        $this->error($this->manage('PATCH', "positions/{$position}", ['unit_id' => 999999]), 422, 'invalid-unit');
        $this->error($this->manage('PATCH', "positions/{$position}", ['min_grade_id' => 999999]), 422, 'invalid-grade');
        $this->error($this->manage('PATCH', "positions/{$position}", ['min_grade_id' => $high, 'max_grade_id' => $low]), 422, 'invalid-grade-range');
        $this->error($this->manage('PATCH', "positions/{$position}", ['job_role_id' => 999999]), 422, 'invalid-job-role');
        $this->error($this->manage('PATCH', "positions/{$position}", ['reports_to_position_id' => $position]), 422, 'invalid-reports-to');
        $this->manage('PATCH', "positions/{$position}", ['min_grade_id' => $low, 'max_grade_id' => $low])->assertOk();
    }

    public function test_reports_to_never_loops_and_a_change_is_dated_like_a_unit(): void
    {
        $unit = $this->unit('FIN', $this->unitType());
        $boss = $this->position('HEAD', $unit);
        $staff = $this->position('STAFF', $unit, ['reports_to_position_id' => $boss]);

        $this->error($this->manage('PATCH', "positions/{$boss}", ['reports_to_position_id' => $staff]), 422, 'reports-to-cycle');

        $this->manage('PATCH', "positions/{$staff}", ['title' => 'Senior staff', 'valid_from' => '2026-12-01'])->assertOk()->assertJsonPath('data.title', 'Position STAFF');
        $this->manage('GET', "positions/{$staff}?as_of=2026-12-01")->assertOk()->assertJsonPath('data.title', 'Senior staff');
        $this->manage('GET', "positions/{$staff}/versions")->assertOk()->assertJsonCount(2, 'data');
        $this->error($this->manage('PATCH', "positions/{$staff}", ['title' => 'Past', 'valid_from' => '2026-10-01']), 422, 'reason-required');
        $this->manage('PATCH', "positions/{$staff}", ['title' => 'Past', 'valid_from' => '2026-10-16'])->assertOk();

        $entry = DB::table('platform_audit_logs')->where('action', 'group.position_changed')->first();
        $this->assertSame('Position STAFF', json_decode((string) $entry->metadata, true)['before']['title']);
    }

    public function test_a_position_that_others_report_to_is_not_deactivated(): void
    {
        $unit = $this->unit('FIN', $this->unitType());
        $boss = $this->position('HEAD', $unit);
        $staff = $this->position('STAFF', $unit, ['reports_to_position_id' => $boss]);

        $this->error($this->manage('POST', "positions/{$boss}/deactivate"), 409, 'position-in-use');
        $this->manage('POST', "positions/{$staff}/deactivate")->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->manage('POST', "positions/{$boss}/deactivate")->assertOk();
        $this->error($this->manage('PATCH', "positions/{$boss}", ['title' => 'Nope']), 409, 'position-inactive');
        $this->manage('POST', "positions/{$boss}/reactivate")->assertOk();
    }
}
