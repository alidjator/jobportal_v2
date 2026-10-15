<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Company\Domain\MemberPreset;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\Job\JobDirectory;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** The team actions of a company admin: renaming, the merged list, suspension, the owner, invitations, the allowed email domains, detail, activity and history. */
final class CompanyTeamManagementTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string PASSWORD = 'password-123';

    private int $companyId;

    private UserModel $owner;

    private UserModel $second;

    private UserModel $recruiter;

    private string $ownerToken;

    private string $secondToken;

    private string $recruiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        config(['app.invitation_url' => 'https://careers.test/invitations/{token}']);

        $this->companyId = $this->makeCompany('PT Maju Bersama', 'pt-maju-bersama');
        $this->owner = $this->makeMember($this->companyId, 'Andi Admin', 'andi@example.test', 'company_admin');
        $this->second = $this->makeMember($this->companyId, 'Cici Admin', 'cici@example.test', 'company_admin');
        $this->recruiter = $this->makeMember($this->companyId, 'Rina Rekruter', 'rina@example.test', 'recruiter');
        $this->ownerToken = $this->tokenOf($this->owner);
        $this->secondToken = $this->tokenOf($this->second);
        $this->recruiterToken = $this->tokenOf($this->recruiter);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeCompany(string $name, string $slug): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => $name, 'slug' => $slug, 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeMember(int $companyId, string $name, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return $user;
    }

    private function tokenOf(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->ownerToken)->json($method, '/api/v1/company'.$uri, $body);
    }

    private function platformAdminToken(array $permissions = ['companies.manage']): string
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    /** @return list<string> the plain text bodies of the emails sent to the address */
    private function mailsTo(string $email): array
    {
        $bodies = [];

        foreach ($this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all() as $message) {
            $original = $message->getOriginalMessage();

            if ($original->getTo()[0]->getAddress() === $email) {
                $bodies[] = (string) $original->getTextBody();
            }
        }

        return $bodies;
    }

    private function invite(string $email = 'new@example.test', string $role = 'member', ?array $permissions = null): TestResponse
    {
        return $this->api('POST', '/invitations', [
            'name' => 'Budi Baru', 'email' => $email, 'role' => $role, 'permissions' => $permissions ?? ['applications.view', 'interviews.manage'],
        ]);
    }

    /** @return list<string> every invitation token in the emails sent so far, oldest first */
    private function invitationTokens(): array
    {
        $tokens = [];

        foreach ($this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all() as $message) {
            if (preg_match('#careers\.test/invitations/([A-Za-z0-9_-]{43})#', (string) $message->getOriginalMessage()->getTextBody(), $matches) === 1) {
                $tokens[] = $matches[1];
            }
        }

        return $tokens;
    }

    private function accept(string $plainToken): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson("/api/v1/auth/invitations/{$plainToken}/accept", ['name' => 'Budi Baru', 'password' => self::PASSWORD]);
    }

    private function actionCount(string $action): int
    {
        return DB::table('platform_audit_logs')->where('action', $action)->count();
    }

    /** @return array<string, mixed> */
    private function lastMetadata(string $action): array
    {
        return json_decode((string) DB::table('platform_audit_logs')->where('action', $action)->orderByDesc('id')->value('metadata'), true);
    }

    // ----- renaming -----

    public function test_a_company_admin_renames_a_member_and_the_change_is_audited_and_notified(): void
    {
        $this->api('PATCH', "/members/{$this->recruiter->id}", ['name' => '  Rina Baru '])->assertNoContent();

        $this->assertSame('Rina Baru', UserModel::query()->findOrFail($this->recruiter->id)->name);
        $this->assertSame(
            ['user_id' => $this->recruiter->id, 'old_name' => 'Rina Rekruter', 'new_name' => 'Rina Baru'],
            $this->lastMetadata('company.member_renamed'),
        );
        $notification = DB::table('notifications')->where('notifiable_id', $this->recruiter->id)->where('type', 'company.member_renamed')->first();
        $this->assertSame(['name' => 'Rina Baru'], json_decode((string) $notification->data, true));
        $this->assertSame('andi@example.test', UserModel::query()->findOrFail($this->owner->id)->email);

        // The same name again changes and audits nothing.
        $this->api('PATCH', "/members/{$this->recruiter->id}", ['name' => 'Rina Baru'])->assertNoContent();
        $this->assertSame(1, $this->actionCount('company.member_renamed'));
    }

    public function test_the_name_can_come_with_a_role_and_permissions_and_must_be_valid(): void
    {
        $this->api('PATCH', "/members/{$this->recruiter->id}", ['name' => 'Rina Baru', 'permissions' => ['applications.view']])->assertNoContent();

        $this->assertSame(1, $this->actionCount('company.member_renamed'));
        $this->assertSame(1, $this->actionCount('company.member_permissions_changed'));

        $this->api('PATCH', "/members/{$this->recruiter->id}", ['name' => ''])->assertStatus(422);
        $this->api('PATCH', "/members/{$this->recruiter->id}", ['name' => str_repeat('x', 101)])->assertStatus(422);
        $this->api('PATCH', "/members/{$this->recruiter->id}", ['email' => 'other@example.test'])->assertNoContent();
        $this->assertSame('rina@example.test', UserModel::query()->findOrFail($this->recruiter->id)->email);
    }

    // ----- the list -----

    public function test_the_list_is_searched_by_name_or_email_and_the_count_ignores_the_search(): void
    {
        $this->makeMember($this->companyId, 'Bambang Rekruter', 'bambang@example.test', 'recruiter');
        $this->invite('bambang.baru@example.test')->assertCreated();

        $found = $this->api('GET', '/members?filter[q]=BAMB')->assertOk();

        $this->assertEqualsCanonicalizing(['member', 'invitation'], array_column($found->json('data'), 'type'));
        $this->assertSame(2, $found->json('meta.total'));
        $this->assertSame(4, $found->json('meta.member_count'));

        $byEmail = $this->api('GET', '/members?filter[q]=rina@')->assertOk();
        $this->assertSame(['Rina Rekruter'], array_column($byEmail->json('data'), 'name'));

        $this->api('GET', '/members?filter[q]=zzzz')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.member_count', 4);
    }

    public function test_the_search_and_the_page_are_validated(): void
    {
        $this->api('GET', '/members?filter[q]=a')->assertStatus(422);
        $this->api('GET', '/members?filter[q]='.str_repeat('a', 101))->assertStatus(422);
        $this->api('GET', '/members?filter[other]=1')->assertStatus(422);
        $this->api('GET', '/members?per_page=101')->assertStatus(422);
    }

    public function test_the_list_is_paginated_with_the_owner_first(): void
    {
        $page = $this->api('GET', '/members?per_page=2&page=1')->assertOk();
        $this->assertSame(['Andi Admin', 'Cici Admin'], array_column($page->json('data'), 'name'));
        $this->assertSame(3, $page->json('meta.total'));
        $this->assertSame(2, $page->json('meta.last_page'));

        $this->assertSame(['Rina Rekruter'], array_column($this->api('GET', '/members?per_page=2&page=2')->assertOk()->json('data'), 'name'));
    }

    public function test_the_row_actions_follow_who_may_do_what_to_whom(): void
    {
        $rows = array_column($this->api('GET', '/members')->assertOk()->json('data'), null, 'name');

        $this->assertSame(['edit'], $rows['Andi Admin']['actions']);
        $this->assertSame(['edit', 'remove', 'suspend', 'make_owner'], $rows['Cici Admin']['actions']);
        $this->assertSame(['edit', 'remove', 'suspend'], $rows['Rina Rekruter']['actions']);

        // Another admin is not the owner: no owner to hand over, and the owner is untouchable.
        $others = array_column($this->api('GET', '/members', [], $this->secondToken)->assertOk()->json('data'), null, 'name');
        $this->assertSame(['edit'], $others['Andi Admin']['actions']);
        $this->assertSame(['edit'], $others['Cici Admin']['actions']);
        $this->assertSame(['edit', 'remove', 'suspend'], $others['Rina Rekruter']['actions']);
    }

    // ----- suspension -----

    public function test_suspending_a_member_ends_the_sessions_blocks_sign_in_and_emails_the_member(): void
    {
        $this->api('POST', "/members/{$this->recruiter->id}/suspend", ['message' => 'Back after the audit.'])->assertNoContent();

        $row = DB::table('company_members')->where('user_id', $this->recruiter->id)->first();
        $this->assertSame('suspended', $row->status);
        $this->assertSame($this->owner->id, (int) $row->suspended_by_user_id);
        $this->assertSame('2026-10-05 09:00:00', $row->suspended_at);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->recruiter->id)->count());
        $this->assertSame('active', UserModel::query()->findOrFail($this->recruiter->id)->status);

        $this->api('GET', '/me', [], $this->recruiterToken)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'rina@example.test', 'password' => self::PASSWORD])
            ->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:membership-suspended');

        $mails = $this->mailsTo('rina@example.test');
        $this->assertCount(1, $mails);
        $this->assertStringContainsString('PT Maju Bersama', $mails[0]);
        $this->assertStringContainsString('Back after the audit.', $mails[0]);

        $this->assertSame(['user_id' => $this->recruiter->id], $this->lastMetadata('company.member_suspended'));

        $listed = array_column($this->api('GET', '/members')->assertOk()->json('data'), null, 'name')['Rina Rekruter'];
        $this->assertSame(['suspended', ['edit', 'remove', 'reactivate']], [$listed['status'], $listed['actions']]);

        // Suspending again changes, sends and audits nothing more.
        $this->api('POST', "/members/{$this->recruiter->id}/suspend")->assertNoContent();
        $this->assertCount(1, $this->mailsTo('rina@example.test'));
        $this->assertSame(1, $this->actionCount('company.member_suspended'));
    }

    public function test_a_suspended_member_keeps_everything_and_gets_no_notification_until_reactivated(): void
    {
        $permissions = DB::table('company_member_permissions')->count();
        $this->api('POST', "/members/{$this->recruiter->id}/suspend")->assertNoContent();

        $this->assertSame($permissions, DB::table('company_member_permissions')->count());

        $this->api('PATCH', "/members/{$this->recruiter->id}", ['permissions' => ['applications.view']])->assertNoContent();
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->recruiter->id)->count());

        $this->api('POST', "/members/{$this->recruiter->id}/reactivate")->assertNoContent();
        $this->api('PATCH', "/members/{$this->recruiter->id}", ['permissions' => ['applications.view', 'interviews.manage']])->assertNoContent();
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->recruiter->id)->where('type', 'company.permissions_changed')->count());
    }

    public function test_reactivating_restores_the_sign_in_and_emails_the_member(): void
    {
        $this->api('POST', "/members/{$this->recruiter->id}/suspend")->assertNoContent();
        $this->api('POST', "/members/{$this->recruiter->id}/reactivate")->assertNoContent();

        $row = DB::table('company_members')->where('user_id', $this->recruiter->id)->first();
        $this->assertSame(['active', null, null], [$row->status, $row->suspended_at, $row->suspended_by_user_id]);
        $this->assertCount(2, $this->mailsTo('rina@example.test'));
        $this->assertSame(['user_id' => $this->recruiter->id], $this->lastMetadata('company.member_reactivated'));

        $this->postJson('/api/v1/auth/login', ['email' => 'rina@example.test', 'password' => self::PASSWORD])->assertOk();

        // Reactivating an active member changes, sends and audits nothing.
        $this->api('POST', "/members/{$this->recruiter->id}/reactivate")->assertNoContent();
        $this->assertSame(1, $this->actionCount('company.member_reactivated'));
    }

    public function test_the_owner_oneself_and_a_member_of_another_company_cannot_be_suspended(): void
    {
        $this->api('POST', "/members/{$this->owner->id}/suspend", [], $this->secondToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:member-is-owner');
        $this->api('POST', "/members/{$this->owner->id}/suspend")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:cannot-suspend-self');
        $outsider = $this->makeMember($this->makeCompany('PT Lain', 'pt-lain'), 'Oki Lain', 'oki@example.test', 'recruiter');
        $this->api('POST', "/members/{$outsider->id}/suspend")->assertNotFound();
        $this->api('POST', "/members/{$this->recruiter->id}/suspend", ['message' => str_repeat('x', 501)])->assertStatus(422);

        $this->assertSame(0, DB::table('company_members')->where('status', 'suspended')->count());
    }

    public function test_a_suspended_admin_does_not_count_as_an_active_one_for_the_last_admin_rule(): void
    {
        $this->api('POST', "/members/{$this->second->id}/suspend")->assertNoContent();
        DB::table('company_members')->where('user_id', $this->owner->id)->update(['is_owner' => false]);

        // The owner flag aside, the only active admin cannot be demoted: the suspended one does not count.
        $this->api('PATCH', "/members/{$this->owner->id}", ['role' => 'member'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:last-company-admin');
    }

    public function test_the_jobs_of_a_suspended_assignee_notify_the_company_admins_instead(): void
    {
        $jobId = (int) DB::table('job_jobs')->insertGetId([
            'company_id' => $this->companyId, 'title' => 'Backend Developer', 'slug' => 'backend-developer', 'status' => 'active',
            'created_by_user_id' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('job_assignments')->insert(['job_id' => $jobId, 'user_id' => $this->recruiter->id, 'created_at' => now()]);
        $this->api('POST', "/members/{$this->recruiter->id}/suspend")->assertNoContent();

        $recipients = $this->app->make(JobDirectory::class)->notificationRecipients($jobId);

        $this->assertEqualsCanonicalizing([$this->owner->id, $this->second->id], $recipients);

        $this->api('POST', "/members/{$this->recruiter->id}/reactivate")->assertNoContent();
        $this->assertSame([$this->recruiter->id], $this->app->make(JobDirectory::class)->notificationRecipients($jobId));
    }

    // ----- the owner -----

    public function test_the_owner_hands_the_ownership_over_with_their_password(): void
    {
        $this->api('POST', "/members/{$this->second->id}/make-owner", ['password' => self::PASSWORD])->assertNoContent();

        $this->assertSame([false, true], [
            (bool) DB::table('company_members')->where('user_id', $this->owner->id)->value('is_owner'),
            (bool) DB::table('company_members')->where('user_id', $this->second->id)->value('is_owner'),
        ]);
        $this->assertSame('company_admin', DB::table('company_members')->where('user_id', $this->owner->id)->value('role'));
        $this->assertCount(1, $this->mailsTo('cici@example.test'));
        $this->assertCount(1, $this->mailsTo('andi@example.test'));
        $this->assertSame(['user_id' => $this->second->id, 'from_user_id' => $this->owner->id], $this->lastMetadata('company.owner_transferred'));
        $this->api('GET', '/me', [], $this->secondToken)->assertOk()->assertJsonPath('data.is_owner', true);
        $this->api('GET', '/me')->assertOk()->assertJsonPath('data.is_owner', false);
    }

    public function test_only_the_owner_hands_the_ownership_over_with_the_right_password_to_an_active_company_admin(): void
    {
        $this->api('POST', "/members/{$this->second->id}/make-owner", ['password' => self::PASSWORD], $this->secondToken)
            ->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:owner-required');
        $this->api('POST', "/members/{$this->second->id}/make-owner", ['password' => 'wrong-password'])
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-password');
        $this->api('POST', "/members/{$this->second->id}/make-owner")->assertStatus(422);
        $this->api('POST', "/members/{$this->recruiter->id}/make-owner", ['password' => self::PASSWORD])
            ->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:owner-target-not-company-admin');
        $outsider = $this->makeMember($this->makeCompany('PT Lain', 'pt-lain'), 'Oki Lain', 'oki@example.test', 'company_admin');
        $this->api('POST', "/members/{$outsider->id}/make-owner", ['password' => self::PASSWORD])->assertNotFound();

        DB::table('company_members')->where('user_id', $this->second->id)->update(['status' => 'suspended']);
        $this->api('POST', "/members/{$this->second->id}/make-owner", ['password' => self::PASSWORD])
            ->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:owner-target-not-company-admin');

        $this->assertTrue((bool) DB::table('company_members')->where('user_id', $this->owner->id)->value('is_owner'));
        $this->assertSame(0, $this->actionCount('company.owner_transferred'));
    }

    public function test_a_platform_admin_names_the_owner_of_a_company(): void
    {
        DB::table('company_members')->update(['is_owner' => false]);
        $token = $this->platformAdminToken();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->putJson("/api/v1/admin/companies/{$this->companyId}/owner", ['user_id' => $this->second->id])->assertNoContent();

        $this->assertTrue((bool) DB::table('company_members')->where('user_id', $this->second->id)->value('is_owner'));
        $entry = DB::table('platform_audit_logs')->where('action', 'company.owner_transferred')->first();
        $this->assertSame('admin', $entry->actor_type);
        $this->assertSame(['user_id' => $this->second->id, 'from_user_id' => null], json_decode((string) $entry->metadata, true));
        $this->assertCount(1, $this->mailsTo('cici@example.test'));
    }

    public function test_the_platform_admin_owner_route_needs_the_permission_and_an_active_company_admin(): void
    {
        $this->putJson("/api/v1/admin/companies/{$this->companyId}/owner", ['user_id' => $this->second->id])->assertStatus(401);

        $token = $this->platformAdminToken();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->putJson("/api/v1/admin/companies/{$this->companyId}/owner", ['user_id' => $this->recruiter->id])
            ->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:owner-target-not-company-admin');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->putJson('/api/v1/admin/companies/999999/owner', ['user_id' => $this->second->id])->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->putJson("/api/v1/admin/companies/{$this->companyId}/owner", [])->assertStatus(422);

        $this->assertTrue((bool) DB::table('company_members')->where('user_id', $this->owner->id)->value('is_owner'));
    }

    public function test_the_first_company_admin_to_join_a_company_is_its_owner(): void
    {
        $fresh = $this->makeCompany('PT Baru', 'pt-baru');
        $token = $this->platformAdminToken();

        foreach (['first@example.test', 'second@example.test'] as $email) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->postJson("/api/v1/admin/companies/{$fresh}/invitations", ['email' => $email])->assertCreated();
        }

        [$firstToken, $secondToken] = $this->invitationTokens();
        $this->accept($firstToken)->assertCreated();
        $this->accept($secondToken)->assertCreated();

        $owners = DB::table('company_members')->where('company_id', $fresh)->where('is_owner', true)->count();
        $this->assertSame(1, $owners);
        $this->assertSame(
            [true, false],
            [
                (bool) DB::table('company_members')->where('user_id', UserModel::query()->where('email', 'first@example.test')->value('id'))->value('is_owner'),
                (bool) DB::table('company_members')->where('user_id', UserModel::query()->where('email', 'second@example.test')->value('id'))->value('is_owner'),
            ],
        );
    }

    public function test_a_member_joining_a_company_is_never_its_owner(): void
    {
        $this->invite()->assertCreated();
        $this->accept($this->invitationTokens()[0])->assertCreated();

        $this->assertSame(1, DB::table('company_members')->where('company_id', $this->companyId)->where('is_owner', true)->count());
    }

    // ----- detail, activity and history -----

    public function test_a_member_is_shown_in_detail(): void
    {
        $jobId = (int) DB::table('job_jobs')->insertGetId([
            'company_id' => $this->companyId, 'title' => 'Backend Developer', 'slug' => 'backend-developer', 'status' => 'active',
            'created_by_user_id' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('job_assignments')->insert(['job_id' => $jobId, 'user_id' => $this->recruiter->id, 'created_at' => now()]);
        $this->recruiter->forceFill(['last_login_at' => '2026-10-04 07:00:00'])->save();
        $this->api('POST', "/members/{$this->recruiter->id}/suspend")->assertNoContent();

        $this->api('GET', "/members/{$this->recruiter->id}")->assertOk()->assertJsonPath('data', [
            'user_id' => $this->recruiter->id, 'name' => 'Rina Rekruter', 'email' => 'rina@example.test', 'role' => 'member', 'is_owner' => false,
            'preset' => 'recruiter', 'permissions' => MemberPreset::Recruiter->permissionNames(), 'status' => 'suspended',
            'suspended_at' => '2026-10-05T09:00:00+00:00', 'suspended_by' => 'Andi Admin', 'joined_at' => '2026-10-05T09:00:00+00:00',
            'last_login_at' => '2026-10-04T07:00:00+00:00', 'assigned_job_count' => 1, 'two_factor' => 'off',
        ]);
        $this->api('GET', "/members/{$this->owner->id}")->assertOk()->assertJsonPath('data.preset', 'company_admin')->assertJsonPath('data.is_owner', true);

        DB::table('company_member_permissions')->where('permission', 'jobs.create')->delete();
        $this->api('GET', "/members/{$this->recruiter->id}")->assertJsonPath('data.preset', null);

        $outsider = $this->makeMember($this->makeCompany('PT Lain', 'pt-lain'), 'Oki Lain', 'oki@example.test', 'recruiter');
        $this->api('GET', "/members/{$outsider->id}")->assertNotFound();
        $this->api('GET', "/members/{$outsider->id}/activity")->assertNotFound();
    }

    public function test_the_activity_of_a_member_comes_from_the_audit_log_with_network_details(): void
    {
        DB::table('platform_audit_logs')->insert([
            ['actor_type' => 'user', 'actor_id' => $this->recruiter->id, 'action' => 'auth.login', 'subject_type' => 'user', 'subject_id' => $this->recruiter->id,
                'metadata' => '{}', 'ip_address' => '203.0.113.7', 'user_agent' => 'Phone/1.0', 'occurred_at' => '2026-10-04 08:00:00'],
            ['actor_type' => 'user', 'actor_id' => $this->recruiter->id, 'action' => 'job.published', 'subject_type' => 'job', 'subject_id' => 55,
                'metadata' => '{}', 'ip_address' => '203.0.113.8', 'user_agent' => 'Phone/1.0', 'occurred_at' => '2026-10-04 09:00:00'],
            ['actor_type' => 'user', 'actor_id' => $this->second->id, 'action' => 'auth.login', 'subject_type' => 'user', 'subject_id' => $this->second->id,
                'metadata' => '{}', 'ip_address' => '203.0.113.9', 'user_agent' => 'Other/1.0', 'occurred_at' => '2026-10-04 10:00:00'],
        ]);

        $response = $this->api('GET', "/members/{$this->recruiter->id}/activity")->assertOk();

        $this->assertSame(['job.published', 'auth.login'], array_column($response->json('data'), 'action'));
        $this->assertSame('203.0.113.8', $response->json('data.0.ip_address'));
        $this->assertSame('Phone/1.0', $response->json('data.0.user_agent'));
        $this->assertSame(['job', 55], [$response->json('data.0.subject_type'), $response->json('data.0.subject_id')]);
        $this->assertNull($response->json('meta.next_cursor'));

        $this->assertSame(['auth.login'], array_column($this->api('GET', "/members/{$this->recruiter->id}/activity?filter[action]=auth.login")->json('data'), 'action'));
        $this->assertSame(
            ['job.published'],
            array_column($this->api('GET', "/members/{$this->recruiter->id}/activity?filter[from]=2026-10-04T08:30:00Z")->json('data'), 'action'),
        );

        $first = $this->api('GET', "/members/{$this->recruiter->id}/activity?per_page=1")->assertOk();
        $this->assertNotNull($first->json('meta.next_cursor'));
        $next = $this->api('GET', "/members/{$this->recruiter->id}/activity?per_page=1&cursor=".$first->json('meta.next_cursor'))->assertOk();
        $this->assertSame(['auth.login'], array_column($next->json('data'), 'action'));
    }

    public function test_the_team_history_lists_the_team_changes_without_network_details(): void
    {
        $this->api('PATCH', "/members/{$this->recruiter->id}", ['name' => 'Rina Baru', 'permissions' => ['applications.view']])->assertNoContent();
        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->api('POST', "/members/{$this->recruiter->id}/suspend")->assertNoContent();
        // An entry about something else, and one about another company, must not show.
        DB::table('platform_audit_logs')->insert([
            ['actor_type' => 'user', 'actor_id' => $this->owner->id, 'action' => 'job.published', 'subject_type' => 'company', 'subject_id' => $this->companyId,
                'metadata' => '{}', 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'occurred_at' => now()],
            ['actor_type' => 'user', 'actor_id' => $this->owner->id, 'action' => 'company.member_suspended', 'subject_type' => 'company', 'subject_id' => 999,
                'metadata' => '{"user_id": 1}', 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'occurred_at' => now()],
        ]);

        $response = $this->api('GET', '/team/history')->assertOk();

        $this->assertSame(
            ['company.member_suspended', 'company.member_permissions_changed', 'company.member_renamed'],
            array_column($response->json('data'), 'action'),
        );
        $first = $response->json('data.0');
        $this->assertSame(['type' => 'user', 'user_id' => $this->owner->id, 'name' => 'Andi Admin'], $first['actor']);
        $this->assertSame(['user_id' => $this->recruiter->id, 'name' => 'Rina Baru', 'email' => 'rina@example.test'], $first['member']);
        $this->assertArrayNotHasKey('ip_address', $first);
        $this->assertArrayNotHasKey('user_agent', $first);
        $this->assertSame('2026-10-05T10:00:00+00:00', $first['occurred_at']);

        $this->assertSame(
            ['company.member_renamed'],
            array_column($this->api('GET', '/team/history?filter[action]=company.member_renamed')->json('data'), 'action'),
        );
        $this->assertCount(3, $this->api('GET', "/team/history?filter[user_id]={$this->recruiter->id}")->json('data'));
        $this->assertCount(0, $this->api('GET', "/team/history?filter[user_id]={$this->second->id}")->json('data'));
        $this->assertCount(1, $this->api('GET', '/team/history?filter[from]=2026-10-05T09:30:00Z')->json('data'));
        $this->api('GET', '/team/history?filter[action]=job.published')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:unknown-team-action');
    }

    public function test_a_platform_admin_shows_in_the_history_without_a_name(): void
    {
        DB::table('company_members')->update(['is_owner' => false]);
        $token = $this->platformAdminToken();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->putJson("/api/v1/admin/companies/{$this->companyId}/owner", ['user_id' => $this->second->id])->assertNoContent();

        $entry = $this->api('GET', '/team/history')->assertOk()->json('data.0');

        $this->assertSame('company.owner_transferred', $entry['action']);
        $this->assertSame(['type' => 'platform_admin', 'user_id' => null, 'name' => null], $entry['actor']);
        $this->assertSame($this->second->id, $entry['member']['user_id']);
    }

    // ----- editing and resending invitations -----

    public function test_a_pending_invitation_is_edited_and_the_link_stays_the_same(): void
    {
        $this->invite()->assertCreated();
        $id = (int) DB::table('company_invitations')->value('id');
        $hash = DB::table('company_invitations')->value('token_hash');

        $this->api('PATCH', "/invitations/{$id}", ['name' => 'Budi Lama', 'permissions' => ['applications.view']])->assertNoContent();

        $row = DB::table('company_invitations')->first();
        $this->assertSame(['Budi Lama', 'member', ['applications.view'], $hash], [$row->name, $row->role, json_decode((string) $row->permissions, true), $row->token_hash]);
        $this->assertSame(
            ['invitation_id' => $id, 'old_role' => 'member', 'role' => 'member', 'permissions' => ['applications.view']],
            $this->lastMetadata('company.invitation_updated'),
        );

        // The invitee still uses the original link and gets the edited permissions.
        $this->accept($this->invitationTokens()[0])->assertCreated();
        $joined = UserModel::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertSame(['applications.view'], DB::table('company_member_permissions')->where('company_member_id', DB::table('company_members')->where('user_id', $joined->id)->value('id'))->pluck('permission')->all());
    }

    public function test_an_invitation_can_change_role_and_the_same_values_change_nothing(): void
    {
        $this->invite()->assertCreated();
        $id = (int) DB::table('company_invitations')->value('id');

        $this->api('PATCH', "/invitations/{$id}", ['name' => 'Budi Baru'])->assertNoContent();
        $this->api('PATCH', "/invitations/{$id}", ['permissions' => ['interviews.manage', 'applications.view']])->assertNoContent();
        $this->assertSame(0, $this->actionCount('company.invitation_updated'));

        $this->api('PATCH', "/invitations/{$id}", ['role' => 'company_admin'])->assertNoContent();
        $this->assertNull(DB::table('company_invitations')->value('permissions'));
        $this->api('PATCH', "/invitations/{$id}", ['role' => 'member'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:permissions-required');
        $this->api('PATCH', "/invitations/{$id}", ['role' => 'member', 'permissions' => ['jobs.publish']])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:permission-requires-view');
        $this->api('PATCH', "/invitations/{$id}", ['role' => 'member', 'permissions' => ['applications.view']])->assertNoContent();
        $this->assertSame(2, $this->actionCount('company.invitation_updated'));
    }

    public function test_only_a_pending_invitation_of_the_own_company_is_edited(): void
    {
        $foreign = (int) DB::table('company_invitations')->insertGetId([
            'company_id' => $this->makeCompany('PT Lain', 'pt-lain'), 'email' => 'other@example.test', 'role' => 'member', 'token_hash' => str_repeat('e', 64),
            'sent_at' => '2026-10-05 08:00:00', 'expires_at' => '2026-10-12 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $expired = (int) DB::table('company_invitations')->insertGetId([
            'company_id' => $this->companyId, 'email' => 'late@example.test', 'role' => 'member', 'token_hash' => str_repeat('f', 64),
            'sent_at' => '2026-09-20 08:00:00', 'expires_at' => '2026-09-27 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api('PATCH', "/invitations/{$foreign}", ['name' => 'X'])->assertNotFound();
        $this->api('PATCH', "/invitations/{$expired}", ['name' => 'X'])->assertNotFound();
    }

    public function test_resending_gives_a_new_link_and_seven_more_days(): void
    {
        $this->invite()->assertCreated();
        $id = (int) DB::table('company_invitations')->value('id');
        Carbon::setTestNow('2026-10-05 09:02:00');

        $this->api('POST', "/invitations/{$id}/resend")->assertNoContent();

        [$old, $new] = $this->invitationTokens();
        $this->assertNotSame($old, $new);
        $row = DB::table('company_invitations')->first();
        $this->assertSame([hash('sha256', $new), '2026-10-05 09:02:00', '2026-10-12 09:02:00'], [$row->token_hash, $row->sent_at, $row->expires_at]);
        $this->assertSame(['invitation_id' => $id], $this->lastMetadata('company.invitation_resent'));
        $this->accept($old)->assertStatus(404);
        $this->accept($new)->assertCreated();
    }

    public function test_a_resend_waits_a_minute_and_an_expired_invitation_can_be_resent(): void
    {
        $this->invite()->assertCreated();
        $id = (int) DB::table('company_invitations')->value('id');

        $this->api('POST', "/invitations/{$id}/resend")->assertStatus(429)->assertJsonPath('type', 'urn:onesis:error:invitation-cooldown');

        Carbon::setTestNow('2026-10-14 09:00:00');
        $token = $this->tokenOf($this->owner);
        $rows = array_values(array_filter($this->api('GET', '/members', [], $token)->json('data'), fn (array $row): bool => $row['type'] === 'invitation'));
        $this->assertSame(['expired', ['resend']], [$rows[0]['status'], $rows[0]['actions']]);

        $this->api('POST', "/invitations/{$id}/resend", [], $token)->assertNoContent();
        $this->assertSame('2026-10-21 09:00:00', DB::table('company_invitations')->value('expires_at'));
        $this->accept($this->invitationTokens()[1])->assertCreated();
    }

    public function test_only_a_pending_or_expired_invitation_of_the_own_company_is_resent(): void
    {
        $foreign = (int) DB::table('company_invitations')->insertGetId([
            'company_id' => $this->makeCompany('PT Lain', 'pt-lain'), 'email' => 'other@example.test', 'role' => 'member', 'token_hash' => str_repeat('e', 64),
            'sent_at' => '2026-10-05 08:00:00', 'expires_at' => '2026-10-12 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $revoked = (int) DB::table('company_invitations')->insertGetId([
            'company_id' => $this->companyId, 'email' => 'gone@example.test', 'role' => 'member', 'token_hash' => str_repeat('f', 64), 'revoked_at' => '2026-10-05 08:10:00',
            'sent_at' => '2026-10-05 08:00:00', 'expires_at' => '2026-10-12 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $existing = (int) DB::table('company_invitations')->insertGetId([
            'company_id' => $this->companyId, 'email' => 'rina@example.test', 'role' => 'member', 'token_hash' => str_repeat('g', 64),
            'sent_at' => '2026-10-05 08:00:00', 'expires_at' => '2026-10-12 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api('POST', "/invitations/{$foreign}/resend")->assertNotFound();
        $this->api('POST', "/invitations/{$revoked}/resend")->assertNotFound();
        $this->api('POST', "/invitations/{$existing}/resend")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:email-already-registered');
    }

    public function test_the_purge_deletes_the_invitations_that_ended_more_than_thirty_days_ago(): void
    {
        $row = fn (string $email, array $times): array => [
            'company_id' => $this->companyId, 'email' => $email, 'role' => 'member', 'token_hash' => hash('sha256', $email),
            'sent_at' => '2026-08-01 08:00:00', 'expires_at' => '2026-10-20 08:00:00', 'accepted_at' => null, 'revoked_at' => null,
            'created_at' => now(), 'updated_at' => now(), ...$times,
        ];
        DB::table('company_invitations')->insert([
            $row('expired-long@example.test', ['expires_at' => '2026-08-08 08:00:00']),
            $row('expired-recent@example.test', ['expires_at' => '2026-09-20 08:00:00']),
            $row('revoked-long@example.test', ['revoked_at' => '2026-08-20 08:00:00']),
            $row('accepted-long@example.test', ['accepted_at' => '2026-08-20 08:00:00']),
            $row('accepted-recent@example.test', ['accepted_at' => '2026-10-01 08:00:00']),
            $row('pending@example.test', []),
        ]);

        $this->assertSame(0, Artisan::call('company:purge-invitations'));

        $this->assertEqualsCanonicalizing(
            ['expired-recent@example.test', 'accepted-recent@example.test', 'pending@example.test'],
            DB::table('company_invitations')->pluck('email')->all(),
        );
    }

    // ----- allowed email domains -----

    public function test_the_allowed_email_domains_are_off_by_default_and_normalised(): void
    {
        $this->api('GET', '/team/email-domains')->assertOk()->assertExactJson(['data' => ['domains' => []]]);

        $this->api('PUT', '/team/email-domains', ['domains' => ['@Example.test', 'example.test', ' PT-Maju.co.id ']])
            ->assertOk()->assertExactJson(['data' => ['domains' => ['example.test', 'pt-maju.co.id']]]);
        $this->assertSame(['before' => null, 'after' => ['example.test', 'pt-maju.co.id']], $this->lastMetadata('company.invitation_email_domains_changed'));

        // The same list again audits nothing; an empty list lifts the limit.
        $this->api('PUT', '/team/email-domains', ['domains' => ['pt-maju.co.id', 'example.test']])->assertOk();
        $this->assertSame(1, $this->actionCount('company.invitation_email_domains_changed'));

        $this->api('PUT', '/team/email-domains', ['domains' => []])->assertOk()->assertExactJson(['data' => ['domains' => []]]);
        $this->assertNull(DB::table('company_companies')->where('id', $this->companyId)->value('invitation_email_domains'));
        $this->assertSame(2, $this->actionCount('company.invitation_email_domains_changed'));
    }

    public function test_the_domains_must_be_valid(): void
    {
        $this->api('PUT', '/team/email-domains', ['domains' => ['not a domain']])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-email-domain');
        $this->api('PUT', '/team/email-domains', ['domains' => ['localhost']])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-email-domain');
        $this->api('PUT', '/team/email-domains', [])->assertStatus(422);
        $this->api('PUT', '/team/email-domains', ['domains' => array_map(fn (int $i): string => "d{$i}.example.test", range(1, 21))])->assertStatus(422);
        $this->assertSame(0, $this->actionCount('company.invitation_email_domains_changed'));
    }

    public function test_invitations_and_resends_of_a_company_admin_follow_the_allowed_domains_but_a_platform_admin_does_not(): void
    {
        $this->invite('late@other.test')->assertCreated();
        $id = (int) DB::table('company_invitations')->value('id');
        $this->api('PUT', '/team/email-domains', ['domains' => ['example.test']])->assertOk();

        $this->invite('x@other.test')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-domain-not-allowed')
            ->assertJsonPath('allowed_domains', ['example.test']);
        $this->invite('x@example.test')->assertCreated();

        Carbon::setTestNow('2026-10-05 09:05:00');
        $this->api('POST', "/invitations/{$id}/resend")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-domain-not-allowed');

        $token = $this->platformAdminToken();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/admin/companies/{$this->companyId}/invitations", ['email' => 'staff@other.test'])->assertCreated();
    }
}
