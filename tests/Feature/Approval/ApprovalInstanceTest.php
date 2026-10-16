<?php

namespace Tests\Feature\Approval;

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
use Src\Shared\Application\Approval\ApprovalFact;
use Src\Shared\Application\Approval\ApprovalGranted;
use Src\Shared\Application\Approval\ApprovalRejected;
use Src\Shared\Application\Approval\ApprovalReturned;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Application\Approval\ApprovalSubjectCatalog;
use Src\Shared\Application\Approval\ApprovalSubjectType;
use Src\Shared\Application\Approval\ApprovalSubmission;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/erp-requisitions.md` section 4: running approvals, the decisions, the delegations, an approver who leaves and the outcome events. */
final class ApprovalInstanceTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string TYPE = 'test.requisition';

    private int $holding;

    private int $group;

    private UserModel $admin;

    private UserModel $requester;

    private UserModel $manager;

    private UserModel $reviewer;

    private UserModel $auditor;

    private UserModel $outsider;

    private int $subject = 100;

    /** @var list<object> */
    private array $outcomes = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->app->make(ApprovalSubjectCatalog::class)->add(new ApprovalSubjectType(
            self::TYPE, 'A requisition of a test.', [new ApprovalFact('hires', ApprovalFact::INTEGER, 'Number of hires')], ['hiring_manager'],
        ));

        $this->holding = $this->companyRowId('pt-holding');
        $this->admin = $this->member($this->holding, 'andi@example.test', 'company_admin');
        $this->requester = $this->member($this->holding, 'rina@example.test', 'recruiter');
        $this->manager = $this->member($this->holding, 'mira@example.test', 'recruiter');
        $this->reviewer = $this->member($this->holding, 'rudi@example.test', 'recruiter');
        $this->auditor = $this->member($this->holding, 'gita@example.test', 'recruiter');
        $this->outsider = $this->member($this->companyRowId('pt-luar'), 'eko@example.test', 'company_admin');

        $created = $this->operator('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $this->operator('PUT', "groups/{$this->group}/legal-entities/{$created->json('data.legal_entities.0.id')}/tenant", ['company_id' => $this->holding])->assertOk();
        $this->operator('PUT', "groups/{$this->group}/members/{$this->admin->id}", ['permissions' => ['group.approvals.manage']])->assertOk();
        $this->operator('PUT', "groups/{$this->group}/members/{$this->auditor->id}", ['permissions' => ['group.audit.view']])->assertOk();

        $this->as($this->admin, 'PUT', '/organization/approval-flows/'.self::TYPE, ['steps' => [
            ['mode' => 'any', 'approvers' => [['kind' => 'relative', 'value' => 'hiring_manager']]],
            [
                'mode' => 'all',
                'conditions' => [['fact' => 'hires', 'operator' => 'gte', 'value' => 3]],
                'approvers' => [['kind' => 'member', 'value' => (string) $this->reviewer->id], ['kind' => 'group_role', 'value' => 'group.audit.view']],
            ],
        ]])->assertOk();

        Event::listen(ApprovalGranted::class, fn (object $event) => $this->outcomes[] = $event);
        Event::listen(ApprovalRejected::class, fn (object $event) => $this->outcomes[] = $event);
        Event::listen(ApprovalReturned::class, fn (object $event) => $this->outcomes[] = $event);
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

    /** @param  array<string, int|bool|string>  $facts */
    private function submit(int $hires = 1, ?UserModel $by = null, ?int $company = null, ?UserModel $manager = null): int
    {
        return $this->app->make(Approvals::class)->submit(new ApprovalSubmission(
            self::TYPE, ++$this->subject, $company ?? $this->holding, ($by ?? $this->requester)->id, 'Backend developer', ['hires' => $hires], ['hiring_manager' => [($manager ?? $this->manager)->id]],
        ));
    }

    private function error(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('type', "urn:onesis:error:{$code}");
    }

    // ----- submission -----

    public function test_a_submission_copies_the_steps_that_apply_and_starts_the_first(): void
    {
        $small = $this->submit(1);
        $big = $this->submit(5);

        $this->as($this->requester, 'GET', "/company/approvals/{$small}")->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonCount(1, 'data.steps')
            ->assertJsonPath('data.steps.0.status', 'pending')->assertJsonPath('data.steps.0.approvers.0.user.name', 'Mira')->assertJsonPath('data.requester.name', 'Rina')
            ->assertJsonPath('data.can_decide', false);

        $detail = $this->as($this->requester, 'GET', "/company/approvals/{$big}")->assertOk()->assertJsonCount(2, 'data.steps');
        $detail->assertJsonPath('data.steps.0.status', 'pending')->assertJsonPath('data.steps.1.status', 'waiting')->assertJsonPath('data.steps.1.mode', 'all')
            ->assertJsonPath('data.steps.1.approvers.1.kind', 'role')->assertJsonPath('data.steps.1.approvers.1.permission', 'group.audit.view');

        $entry = DB::table('platform_audit_logs')->where('action', 'approval.submitted')->first();
        $this->assertSame($this->holding, (int) $entry->company_id);
        $this->assertSame($this->group, (int) $entry->group_id);
    }

    public function test_a_submission_is_refused_without_a_flow_for_an_external_tenant_or_twice_at_once(): void
    {
        $approvals = $this->app->make(Approvals::class);
        $submission = fn (int $company, int $subject = 900): ApprovalSubmission => new ApprovalSubmission(self::TYPE, $subject, $company, $this->requester->id, 'X', ['hires' => 1]);

        try {
            $approvals->submit($submission($this->companyRowId('pt-luar')));
            $this->fail('An external tenant has no approvals.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('approval-not-internal-tenant', $e->errorCode());
        }

        try {
            $approvals->submit(new ApprovalSubmission('test.unknown', 1, $this->holding, $this->requester->id, 'X', []));
            $this->fail('An unregistered subject type.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('approval-unknown-subject-type', $e->errorCode());
        }

        $this->submit(1);
        $again = new ApprovalSubmission(self::TYPE, $this->subject, $this->holding, $this->requester->id, 'X', ['hires' => 1]);

        try {
            $approvals->submit($again);
            $this->fail('The subject is already in approval.');
        } catch (StateConflict $e) {
            $this->assertSame('approval-already-pending', $e->errorCode());
        }

        $this->as($this->admin, 'DELETE', '/organization/approval-flows/'.self::TYPE)->assertNoContent();

        try {
            $approvals->submit($submission($this->holding, 901));
            $this->fail('No flow.');
        } catch (StateConflict $e) {
            $this->assertSame('approval-flow-missing', $e->errorCode());
        }
    }

    public function test_a_step_only_the_requester_could_approve_is_refused_and_the_requesters_own_place_is_dropped(): void
    {
        $approvals = $this->app->make(Approvals::class);
        $own = fn (string $mode, array $approvers): array => ['steps' => [['mode' => $mode, 'approvers' => $approvers]]];
        $member = fn (UserModel $user): array => ['kind' => 'member', 'value' => (string) $user->id];
        $submit = fn (): int => $approvals->submit(new ApprovalSubmission(self::TYPE, ++$this->subject, $this->holding, $this->requester->id, 'X', ['hires' => 1]));

        $this->as($this->admin, 'PUT', '/company/approval-flows/'.self::TYPE, $own('any', [$member($this->requester)]))->assertOk();
        try {
            $submit();
            $this->fail('Only the requester could approve.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('approval-requester-only-approver', $e->errorCode());
        }

        $this->as($this->admin, 'PUT', '/company/approval-flows/'.self::TYPE, $own('all', [$member($this->requester), $member($this->manager)]))->assertOk();
        try {
            $submit();
            $this->fail('An all step that names the requester could never finish.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('approval-requester-only-approver', $e->errorCode());
        }

        $this->as($this->admin, 'PUT', '/company/approval-flows/'.self::TYPE, $own('any', [$member($this->requester), $member($this->manager)]))->assertOk();
        $id = $submit();
        $this->as($this->requester, 'GET', "/company/approvals/{$id}")->assertJsonCount(1, 'data.steps.0.approvers')->assertJsonPath('data.steps.0.approvers.0.user.name', 'Mira');
    }

    public function test_a_flow_without_an_applicable_step_approves_at_once(): void
    {
        $this->as($this->admin, 'PUT', '/company/approval-flows/'.self::TYPE, ['steps' => [[
            'mode' => 'any', 'conditions' => [['fact' => 'hires', 'operator' => 'gte', 'value' => 10]], 'approvers' => [['kind' => 'relative', 'value' => 'hiring_manager']],
        ]]])->assertOk();

        $id = $this->submit(1);

        $this->as($this->requester, 'GET', "/company/approvals/{$id}")->assertJsonPath('data.status', 'approved')->assertJsonCount(0, 'data.steps');
        $this->assertCount(1, $this->outcomes);
        $this->assertInstanceOf(ApprovalGranted::class, $this->outcomes[0]);
    }

    // ----- decisions -----

    public function test_the_steps_run_in_order_and_each_mode_decides_when_a_step_is_done(): void
    {
        $id = $this->submit(5);

        $this->as($this->manager, 'GET', '/company/approvals')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.can_decide', true);
        $this->as($this->reviewer, 'GET', '/company/approvals')->assertJsonCount(0, 'data');
        $this->error($this->as($this->reviewer, 'POST', "/company/approvals/{$id}/approve"), 403, 'approval-decision-not-allowed');
        $this->error($this->as($this->requester, 'POST', "/company/approvals/{$id}/approve"), 403, 'approval-decision-not-allowed');

        $this->as($this->manager, 'POST', "/company/approvals/{$id}/approve")->assertOk()->assertJsonPath('data.steps.0.status', 'approved')->assertJsonPath('data.steps.1.status', 'pending')
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.steps.0.approvers.0.decided_by.name', 'Mira');
        $this->error($this->as($this->manager, 'POST', "/company/approvals/{$id}/approve"), 403, 'approval-decision-not-allowed');

        $this->as($this->reviewer, 'GET', '/company/approvals')->assertJsonPath('data.0.id', $id);
        $this->as($this->auditor, 'GET', '/company/approvals')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.can_decide', true);

        $this->as($this->reviewer, 'POST', "/company/approvals/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertSame([], $this->outcomes);

        $this->as($this->auditor, 'POST', "/company/approvals/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.can_decide', false);

        $this->assertCount(1, $this->outcomes);
        $this->assertInstanceOf(ApprovalGranted::class, $this->outcomes[0]);
        $this->assertSame([$id, self::TYPE, $this->subject, $this->holding, $this->requester->id], [$this->outcomes[0]->approvalId, $this->outcomes[0]->subjectType, $this->outcomes[0]->subjectId, $this->outcomes[0]->companyId, $this->outcomes[0]->requesterId]);
        $this->assertSame(3, DB::table('platform_audit_logs')->where('action', 'approval.step_approved')->count());
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'approval.granted')->count());
        $this->error($this->as($this->auditor, 'POST', "/company/approvals/{$id}/approve"), 409, 'approval-not-pending');
    }

    public function test_a_rejection_or_a_return_needs_a_reason_ends_the_approval_and_tells_the_owner(): void
    {
        $id = $this->submit(1);

        $this->as($this->manager, 'POST', "/company/approvals/{$id}/reject", ['reason' => 'no'])->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->as($this->manager, 'POST', "/company/approvals/{$id}/return", [])->assertStatus(422);

        $this->as($this->manager, 'POST', "/company/approvals/{$id}/return", ['reason' => 'Add the budget'])->assertOk()->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.outcome.reason', 'Add the budget')->assertJsonPath('data.outcome.by.name', 'Mira');
        $this->assertInstanceOf(ApprovalReturned::class, $this->outcomes[0]);
        $this->assertSame('Add the budget', $this->outcomes[0]->reason);
        $this->error($this->as($this->manager, 'POST', "/company/approvals/{$id}/reject", ['reason' => 'Too late now']), 409, 'approval-not-pending');

        $again = $this->app->make(Approvals::class)->submit(new ApprovalSubmission(self::TYPE, $this->subject, $this->holding, $this->requester->id, 'Backend developer', ['hires' => 1], ['hiring_manager' => [$this->manager->id]]));
        $this->as($this->manager, 'POST', "/company/approvals/{$again}/reject", ['reason' => 'Not this year'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertInstanceOf(ApprovalRejected::class, $this->outcomes[1]);

        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'approval.returned')->count());
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'approval.rejected')->count());
    }

    public function test_editing_the_flow_never_changes_a_running_approval(): void
    {
        $id = $this->submit(5);

        $this->as($this->admin, 'PUT', '/organization/approval-flows/'.self::TYPE, ['steps' => [['mode' => 'any', 'approvers' => [['kind' => 'relative', 'value' => 'hiring_manager']]]]])->assertOk();

        $this->as($this->requester, 'GET', "/company/approvals/{$id}")->assertJsonCount(2, 'data.steps');
    }

    public function test_the_requester_lists_what_they_asked_for_and_outsiders_see_nothing(): void
    {
        $this->submit(1);
        $this->submit(1, $this->reviewer);

        $this->as($this->requester, 'GET', '/company/approvals?as=requester')->assertOk()->assertJsonCount(1, 'data');
        $this->as($this->outsider, 'GET', '/company/approvals')->assertOk()->assertJsonCount(0, 'data');
        $this->as($this->outsider, 'GET', '/company/approvals/1')->assertNotFound();
    }

    public function test_a_cancelled_approval_takes_no_more_decisions(): void
    {
        $id = $this->submit(1);

        $this->app->make(Approvals::class)->cancel(self::TYPE, $this->subject, $this->requester->id);

        $this->as($this->requester, 'GET', "/company/approvals/{$id}")->assertJsonPath('data.status', 'cancelled');
        $this->error($this->as($this->manager, 'POST', "/company/approvals/{$id}/approve"), 409, 'approval-not-pending');
        $this->app->make(Approvals::class)->cancel(self::TYPE, 99999, $this->requester->id);
    }

    // ----- delegation -----

    public function test_a_delegate_decides_in_the_approvers_place_and_both_names_are_recorded(): void
    {
        $id = $this->submit(1);

        $this->as($this->manager, 'POST', '/company/approval-delegations', ['delegate_user_id' => $this->reviewer->id, 'from' => '2026-10-17', 'to' => '2026-10-20'])->assertCreated()
            ->assertJsonPath('data.from', '2026-10-17');
        $this->error($this->as($this->reviewer, 'POST', "/company/approvals/{$id}/approve"), 404, 'not-found');

        Carbon::setTestNow('2026-10-18 09:00:00');
        $this->as($this->reviewer, 'GET', '/company/approvals')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.can_decide', true);
        $this->as($this->reviewer, 'POST', "/company/approvals/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.steps.0.approvers.0.decided_by.name', 'Rudi')->assertJsonPath('data.steps.0.approvers.0.on_behalf_of.name', 'Mira');
        $this->assertSame($this->manager->id, (int) json_decode((string) DB::table('platform_audit_logs')->where('action', 'approval.step_approved')->first()->metadata, true)['on_behalf_of']);
    }

    public function test_a_delegation_ends_with_its_range_and_is_checked(): void
    {
        $post = fn (array $body): TestResponse => $this->as($this->manager, 'POST', '/company/approval-delegations', $body);

        $this->error($post(['delegate_user_id' => $this->manager->id, 'from' => '2026-10-16', 'to' => '2026-10-17']), 422, 'invalid-delegate');
        $this->error($post(['delegate_user_id' => $this->outsider->id, 'from' => '2026-10-16', 'to' => '2026-10-17']), 422, 'invalid-delegate');
        $this->error($post(['delegate_user_id' => $this->reviewer->id, 'from' => '2026-10-15', 'to' => '2026-10-17']), 422, 'invalid-delegation-range');
        $this->error($post(['delegate_user_id' => $this->reviewer->id, 'from' => '2026-10-18', 'to' => '2026-10-17']), 422, 'invalid-delegation-range');
        $this->error($post(['delegate_user_id' => $this->reviewer->id, 'from' => '2026-10-16', 'to' => '2027-01-20']), 422, 'invalid-delegation-range');
        $post(['delegate_user_id' => $this->reviewer->id, 'from' => '2026-10-16'])->assertStatus(422)->assertJsonValidationErrors(['to']);

        $id = (int) $post(['delegate_user_id' => $this->reviewer->id, 'from' => '2026-10-16', 'to' => '2026-10-16'])->assertCreated()->json('data.id');
        $this->as($this->manager, 'GET', '/company/approval-delegations')->assertOk()->assertJsonCount(1, 'data');
        $this->as($this->reviewer, 'DELETE', "/company/approval-delegations/{$id}")->assertNotFound();

        $approval = $this->submit(1);
        $this->as($this->reviewer, 'GET', "/company/approvals/{$approval}")->assertOk();

        Carbon::setTestNow('2026-10-17 09:00:00');
        $this->as($this->reviewer, 'GET', "/company/approvals/{$approval}")->assertNotFound();
        $this->as($this->manager, 'GET', '/company/approval-delegations')->assertJsonCount(0, 'data');

        $this->as($this->manager, 'DELETE', "/company/approval-delegations/{$id}")->assertNoContent();
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'approval.delegation_created')->count());
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'approval.delegation_removed')->count());
    }

    // ----- an approver who leaves -----

    public function test_a_place_whose_account_left_waits_for_a_replacement_named_by_an_admin(): void
    {
        $id = $this->submit(1);

        event(new CompanyMemberRemoved($this->holding, $this->manager->id, 'member', $this->admin->id, null, null, null, now()->toDateTimeImmutable()));

        $detail = $this->as($this->admin, 'GET', "/company/approvals/{$id}")->assertOk()->assertJsonPath('data.steps.0.approvers.0.status', 'vacant')->assertJsonPath('data.can_replace', true);
        $slot = (int) $detail->json('data.steps.0.approvers.0.id');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'approval.approver_vacated')->count());

        $this->error($this->as($this->requester, 'PUT', "/company/approvals/{$id}/approvers/{$slot}", ['user_id' => $this->reviewer->id]), 404, 'not-found');
        $this->error($this->as($this->admin, 'PUT', "/company/approvals/{$id}/approvers/{$slot}", ['user_id' => $this->outsider->id]), 422, 'invalid-approver');
        $this->error($this->as($this->admin, 'PUT', "/company/approvals/{$id}/approvers/{$slot}", ['user_id' => $this->requester->id]), 422, 'invalid-approver');

        $this->as($this->admin, 'PUT', "/company/approvals/{$id}/approvers/{$slot}", ['user_id' => $this->reviewer->id])->assertOk()
            ->assertJsonPath('data.steps.0.approvers.0.status', 'open')->assertJsonPath('data.steps.0.approvers.0.user.name', 'Rudi');
        $this->error($this->as($this->admin, 'PUT', "/company/approvals/{$id}/approvers/{$slot}", ['user_id' => $this->auditor->id]), 409, 'approval-slot-not-vacant');

        $this->as($this->reviewer, 'POST', "/company/approvals/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'approval.approver_replaced')->count());
    }

    public function test_a_relative_role_that_names_nobody_starts_with_a_vacant_place(): void
    {
        $id = $this->app->make(Approvals::class)->submit(new ApprovalSubmission(self::TYPE, 777, $this->holding, $this->requester->id, 'X', ['hires' => 1], []));

        $this->as($this->admin, 'GET', "/company/approvals/{$id}")->assertJsonPath('data.steps.0.approvers.0.status', 'vacant')->assertJsonPath('data.steps.0.approvers.0.user', null);
    }
}
