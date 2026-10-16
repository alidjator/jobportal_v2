<?php

namespace Tests\Feature\Approval;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Approval\Infrastructure\Mail\ApprovalNoticeMail;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Shared\Application\Approval\ApprovalFact;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Application\Approval\ApprovalSubjectCatalog;
use Src\Shared\Application\Approval\ApprovalSubjectType;
use Src\Shared\Application\Approval\ApprovalSubmission;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/erp-requisitions.md` section 4: the notifications to the approvers and the requester, and the reminders. */
final class ApprovalNotificationTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string TYPE = 'test.requisition';

    private int $holding;

    private UserModel $admin;

    private UserModel $requester;

    private UserModel $manager;

    private UserModel $reviewer;

    private UserModel $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-16 09:00:00');
        Mail::fake();

        $this->app->make(ApprovalSubjectCatalog::class)->add(new ApprovalSubjectType(
            self::TYPE, 'A requisition of a test.', [new ApprovalFact('hires', ApprovalFact::INTEGER, 'Number of hires')], ['hiring_manager'],
        ));

        $this->holding = $this->companyRowId('pt-holding');
        $this->admin = $this->member('andi@example.test', 'company_admin');
        $this->requester = $this->member('rina@example.test', 'recruiter');
        $this->manager = $this->member('mira@example.test', 'recruiter');
        $this->reviewer = $this->member('rudi@example.test', 'recruiter');
        $this->auditor = $this->member('gita@example.test', 'recruiter');

        $created = $this->operator('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding', 'legal_form' => 'pt']])->assertCreated();
        $group = (int) $created->json('data.id');
        $this->operator('PUT', "groups/{$group}/legal-entities/{$created->json('data.legal_entities.0.id')}/tenant", ['company_id' => $this->holding])->assertOk();
        $this->operator('PUT', "groups/{$group}/members/{$this->admin->id}", ['permissions' => ['group.approvals.manage']])->assertOk();
        $this->operator('PUT', "groups/{$group}/members/{$this->auditor->id}", ['permissions' => ['group.audit.view']])->assertOk();

        $this->as($this->admin, 'PUT', '/organization/approval-flows/'.self::TYPE, ['steps' => [
            ['mode' => 'any', 'approvers' => [['kind' => 'relative', 'value' => 'hiring_manager']]],
            ['mode' => 'all', 'approvers' => [['kind' => 'member', 'value' => (string) $this->reviewer->id], ['kind' => 'group_role', 'value' => 'group.audit.view']]],
        ]])->assertOk();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('password-123'), 'status' => 'active', 'locale' => 'en',
            'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($this->holding, $user->id, $role);

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

    private function submit(int $subject = 500, int $company = 0): int
    {
        return $this->app->make(Approvals::class)->submit(new ApprovalSubmission(
            self::TYPE, $subject, $company ?: $this->holding, $this->requester->id, 'Backend developer', ['hires' => 1], ['hiring_manager' => [$this->manager->id]],
        ));
    }

    /** @return list<string> the types of the in-app notifications of the account, sorted (notifications written in one second have no other order) */
    private function inApp(UserModel $user): array
    {
        $types = DB::table('notifications')->where('notifiable_id', $user->id)->pluck('type')->all();
        sort($types);

        return $types;
    }

    private function emailsTo(UserModel $user): int
    {
        return Mail::queued(ApprovalNoticeMail::class, fn (ApprovalNoticeMail $mail): bool => $mail->hasTo($user->email))->count();
    }

    private function remind(): int
    {
        Artisan::call('approvals:send-reminders');

        return (int) preg_replace('/\D+/', '', Artisan::output());
    }

    public function test_the_approvers_of_a_step_are_told_when_it_reaches_them_and_nobody_else(): void
    {
        $id = $this->submit();

        $this->assertSame(['approval.requested'], $this->inApp($this->manager));
        $this->assertSame([], $this->inApp($this->requester));
        $this->assertSame([], $this->inApp($this->reviewer), 'the second step has not started');
        $this->assertSame(1, $this->emailsTo($this->manager));

        $data = json_decode((string) DB::table('notifications')->where('notifiable_id', $this->manager->id)->value('data'), true);
        $this->assertSame([$id, self::TYPE, 500, 'Backend developer'], [$data['approval_id'], $data['subject_type'], $data['subject_id'], $data['title']]);

        $this->as($this->manager, 'POST', "/company/approvals/{$id}/approve")->assertOk();

        $this->assertSame(['approval.requested'], $this->inApp($this->reviewer));
        $this->assertSame(['approval.requested'], $this->inApp($this->auditor), 'a holder of the group permission is told too');
        $this->assertSame([], $this->inApp($this->requester));

        $this->as($this->reviewer, 'POST', "/company/approvals/{$id}/approve")->assertOk();
        $this->assertSame(['approval.requested'], $this->inApp($this->auditor), 'a step that is only partly approved tells nobody');
        $this->assertSame([], $this->inApp($this->requester));

        $this->as($this->auditor, 'POST', "/company/approvals/{$id}/approve")->assertOk();
        $this->assertSame(['approval.granted'], $this->inApp($this->requester));
        $this->assertSame(1, $this->emailsTo($this->requester));
    }

    public function test_the_requester_hears_a_rejection_a_return_and_an_approver_who_left(): void
    {
        $id = $this->submit();
        $this->as($this->manager, 'POST', "/company/approvals/{$id}/reject", ['reason' => 'Not this year'])->assertOk();

        $this->assertSame(['approval.rejected'], $this->inApp($this->requester));
        $data = json_decode((string) DB::table('notifications')->where('notifiable_id', $this->requester->id)->value('data'), true);
        $this->assertSame('Not this year', $data['reason']);

        $second = $this->submit(501);
        $this->as($this->manager, 'POST', "/company/approvals/{$second}/return", ['reason' => 'Add the budget'])->assertOk();
        $this->assertSame(['approval.rejected', 'approval.returned'], $this->inApp($this->requester));

        $third = $this->submit(502);
        event(new CompanyMemberRemoved($this->holding, $this->manager->id, 'member', $this->admin->id, null, null, null, now()->toDateTimeImmutable()));
        $this->assertSame(['approval.approver_vacated', 'approval.rejected', 'approval.returned'], $this->inApp($this->requester));
        $this->assertSame('pending', DB::table('approval_instances')->where('id', $third)->value('status'));
    }

    public function test_the_email_follows_the_preference_and_the_in_app_notification_is_always_written(): void
    {
        $this->as($this->requester, 'PUT', '/me/notification-preferences/approval_update/email', ['enabled' => false])->assertNoContent();

        $id = $this->submit();
        $this->as($this->manager, 'POST', "/company/approvals/{$id}/reject", ['reason' => 'Not this year'])->assertOk();

        $this->assertSame(['approval.rejected'], $this->inApp($this->requester));
        $this->assertSame(0, $this->emailsTo($this->requester));

        $this->as($this->manager, 'PUT', '/me/notification-preferences/approval_task/email', ['enabled' => false])->assertNoContent();
        Mail::fake();
        $this->submit(503);
        $this->assertSame(0, $this->emailsTo($this->manager));
        $this->assertSame(['approval.requested', 'approval.requested'], $this->inApp($this->manager));
    }

    // ----- reminders -----

    public function test_an_approval_is_reminded_after_two_days_and_again_every_two_days(): void
    {
        $this->submit();

        Carbon::setTestNow('2026-10-17 09:00:00');
        $this->assertSame(0, $this->remind());

        Carbon::setTestNow('2026-10-18 09:00:00');
        $this->assertSame(1, $this->remind());
        $this->assertSame(['approval.reminder', 'approval.requested'], $this->inApp($this->manager));
        $this->assertSame(2, $this->emailsTo($this->manager));

        Carbon::setTestNow('2026-10-19 09:00:00');
        $this->assertSame(0, $this->remind());

        Carbon::setTestNow('2026-10-20 09:00:00');
        $this->assertSame(1, $this->remind());
        $this->assertSame(['approval.reminder', 'approval.reminder', 'approval.requested'], $this->inApp($this->manager));
        $this->assertSame([], $this->inApp($this->requester));
    }

    public function test_a_company_may_change_the_number_of_days_and_a_decided_approval_is_not_reminded(): void
    {
        $this->app->make(SettingsStore::class)->setCompanyOverride('approvals.reminder_days', $this->holding, 1, null);
        $id = $this->submit();

        Carbon::setTestNow('2026-10-17 09:00:00');
        $this->assertSame(1, $this->remind());

        $this->as($this->manager, 'POST', "/company/approvals/{$id}/reject", ['reason' => 'Not this year'])->assertOk();
        Carbon::setTestNow('2026-10-19 09:00:00');
        $this->assertSame(0, $this->remind());
    }

    public function test_the_second_step_is_reminded_to_the_people_who_still_hold_an_open_place(): void
    {
        $id = $this->submit();
        $this->as($this->manager, 'POST', "/company/approvals/{$id}/approve")->assertOk();
        $this->as($this->reviewer, 'POST', "/company/approvals/{$id}/approve")->assertOk();

        Carbon::setTestNow('2026-10-18 09:00:00');
        $this->assertSame(1, $this->remind());

        $this->assertSame(['approval.reminder', 'approval.requested'], $this->inApp($this->auditor));
        $this->assertSame(['approval.requested'], $this->inApp($this->reviewer), 'the reviewer already approved: no reminder');
        $this->assertSame(['approval.requested'], $this->inApp($this->manager));
    }
}
