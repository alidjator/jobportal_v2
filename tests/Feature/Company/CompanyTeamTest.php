<?php

namespace Tests\Feature\Company;

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

final class CompanyTeamTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string PASSWORD = 'password-123';

    private int $companyId;

    private UserModel $admin;

    private UserModel $recruiter;

    private string $adminToken;

    private string $recruiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        config(['app.invitation_url' => 'https://careers.test/invitations/{token}']);

        $this->companyId = $this->makeCompany('PT Maju Bersama', 'pt-maju-bersama');
        $this->admin = $this->makeMember($this->companyId, 'Andi Admin', 'andi@example.test', 'company_admin');
        $this->recruiter = $this->makeMember($this->companyId, 'Rina Rekruter', 'rina@example.test', 'recruiter');
        $this->adminToken = $this->tokenOf($this->admin);
        $this->recruiterToken = $this->tokenOf($this->recruiter);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeCompany(string $name, string $slug, string $status = 'active'): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => $name, 'slug' => $slug, 'status' => $status, 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeMember(int $companyId, string $name, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        DB::table('company_members')->insert(['company_id' => $companyId, 'user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function tokenOf(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null, string $language = 'en'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->withHeader('Accept-Language', $language)->json($method, '/api/v1/company'.$uri, $body);
    }

    private function invite(string $email = 'new@example.test', string $role = 'recruiter', ?string $token = null): TestResponse
    {
        return $this->api('POST', '/invitations', ['email' => $email, 'role' => $role], $token);
    }

    private function tokenFromMail(int $index = 0): string
    {
        $messages = $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertArrayHasKey($index, $messages->all());

        preg_match('#careers\.test/invitations/([A-Za-z0-9_-]{43})#', (string) $messages[$index]->getOriginalMessage()->getTextBody(), $matches);
        $this->assertNotEmpty($matches, 'The invitation email holds no invitation link.');

        return $matches[1];
    }

    private function acceptInvitation(string $plainToken, string $name = 'Budi Baru'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson("/api/v1/auth/invitations/{$plainToken}/accept", ['name' => $name, 'password' => self::PASSWORD]);
    }

    private function platformAdminToken(array $permissions = ['companies.manage'], string $email = 'ops@example.test'): string
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    // ----- access -----

    public function test_every_team_route_needs_a_signed_in_user(): void
    {
        foreach ([['GET', '/invitations'], ['POST', '/invitations'], ['DELETE', '/invitations/1'], ['GET', '/members'], ['DELETE', '/members/1'], ['PUT', '/members/1/role']] as [$method, $uri]) {
            $this->json($method, '/api/v1/company'.$uri)->assertStatus(401);
        }
    }

    public function test_a_recruiter_cannot_manage_the_team(): void
    {
        foreach ([
            ['GET', '/invitations', []], ['POST', '/invitations', ['email' => 'x@example.test', 'role' => 'recruiter']], ['DELETE', '/invitations/1', []],
            ['GET', '/members', []], ['DELETE', "/members/{$this->admin->id}", []], ['PUT', "/members/{$this->recruiter->id}/role", ['role' => 'company_admin']],
        ] as [$method, $uri, $body]) {
            $this->api($method, $uri, $body, $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-admin-required');
        }

        $this->assertSame(2, DB::table('company_members')->count());
        $this->assertSame(0, DB::table('company_invitations')->count());
    }

    public function test_a_job_seeker_and_a_member_without_a_company_are_refused(): void
    {
        $seeker = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en']);
        $orphan = UserModel::query()->create(['type' => 'company_member', 'name' => 'Orphan', 'email' => 'orphan@example.test', 'status' => 'active', 'locale' => 'en']);

        foreach ([$seeker, $orphan] as $user) {
            $this->api('GET', '/members', [], $this->tokenOf($user))->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
        }
    }

    public function test_a_suspended_company_cannot_manage_its_team(): void
    {
        DB::table('company_companies')->update(['status' => 'suspended']);

        $this->api('GET', '/members')->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
        $this->invite()->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
    }

    // ----- inviting -----

    public function test_a_company_admin_invites_a_colleague_who_can_then_join(): void
    {
        $response = $this->invite('New@Example.test', 'recruiter')->assertCreated();

        $response->assertJsonPath('data.email', 'new@example.test')
            ->assertJsonPath('data.role', 'recruiter')
            ->assertJsonPath('data.sent_at', '2026-10-05T09:00:00+00:00')
            ->assertJsonPath('data.expires_at', '2026-10-12T09:00:00+00:00');
        $this->assertArrayNotHasKey('token', $response->json('data'));

        $row = DB::table('company_invitations')->first();
        $this->assertSame($this->companyId, (int) $row->company_id);
        $this->assertSame($this->admin->id, (int) $row->invited_by_user_id);
        $this->assertNull($row->invited_by_admin_id);
        $this->assertSame(hash('sha256', $this->tokenFromMail()), $row->token_hash);

        $this->acceptInvitation($this->tokenFromMail())->assertCreated();
        $this->assertSame('recruiter', DB::table('company_members')->where('company_id', $this->companyId)->orderByDesc('id')->value('role'));
        $this->assertSame(3, DB::table('company_members')->where('company_id', $this->companyId)->count());
    }

    public function test_the_invitation_email_follows_the_request_language(): void
    {
        $this->api('POST', '/invitations', ['email' => 'new@example.test', 'role' => 'company_admin'], null, 'id')->assertCreated();

        $message = $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()[0]->getOriginalMessage();
        $this->assertSame('Undangan bergabung dengan PT Maju Bersama', $message->getSubject());
        $this->assertStringContainsString('sebagai Admin perusahaan', (string) $message->getTextBody());
    }

    public function test_the_invitation_is_validated_and_refused_for_an_existing_account(): void
    {
        $this->invite('not-an-email')->assertStatus(422);
        $this->invite('new@example.test', 'owner')->assertStatus(422);
        $this->api('POST', '/invitations', ['email' => 'new@example.test'])->assertStatus(422);

        $this->invite('RINA@example.test')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:email-already-registered');

        $this->assertSame(0, DB::table('company_invitations')->count());
    }

    public function test_nothing_is_stored_when_the_invitation_link_is_not_configured(): void
    {
        config(['app.invitation_url' => '']);

        $this->invite()->assertStatus(503)->assertJsonPath('type', 'urn:jobportal:error:invitation-link-unavailable');
        $this->assertSame(0, DB::table('company_invitations')->count());
    }

    public function test_inviting_the_same_email_again_needs_a_minute_and_replaces_the_old_invitation(): void
    {
        $this->invite()->assertCreated();
        $first = $this->tokenFromMail(0);

        Carbon::setTestNow('2026-10-05 09:00:30');
        $this->invite()->assertStatus(429)->assertJsonPath('type', 'urn:jobportal:error:invitation-cooldown')->assertHeader('Retry-After', '30');
        $this->assertSame(1, DB::table('company_invitations')->count());

        Carbon::setTestNow('2026-10-05 09:01:00');
        $this->invite()->assertCreated();

        $this->assertSame(2, DB::table('company_invitations')->count());
        $this->assertSame(1, DB::table('company_invitations')->whereNull('revoked_at')->count());
        $this->acceptInvitation($first)->assertStatus(404);
        $this->acceptInvitation($this->tokenFromMail(1))->assertCreated();
    }

    public function test_another_email_or_another_company_is_not_held_back_by_the_cooldown(): void
    {
        $this->invite('one@example.test')->assertCreated();
        $this->invite('two@example.test')->assertCreated();

        $otherCompany = $this->makeCompany('PT Lain', 'pt-lain');
        $otherAdmin = $this->makeMember($otherCompany, 'Oki Admin', 'oki@example.test', 'company_admin');
        $this->invite('one@example.test', 'recruiter', $this->tokenOf($otherAdmin))->assertCreated();

        $this->assertSame(3, DB::table('company_invitations')->whereNull('revoked_at')->count());
    }

    // ----- pending invitations -----

    public function test_the_list_shows_only_pending_invitations_of_the_own_company_newest_first(): void
    {
        $this->invite('first@example.test')->assertCreated();
        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->invite('second@example.test', 'company_admin')->assertCreated();
        $second = (int) DB::table('company_invitations')->where('email', 'second@example.test')->value('id');
        DB::table('company_invitations')->insert([
            'company_id' => $this->companyId, 'email' => 'by-platform@example.test', 'role' => 'company_admin', 'token_hash' => str_repeat('a', 64),
            'invited_by_admin_id' => 1, 'sent_at' => '2026-10-05 08:00:00', 'expires_at' => '2026-10-12 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Not pending: accepted, revoked, expired; and one of another company.
        foreach ([['accepted_at', '2026-10-05 08:30:00', 'b'], ['revoked_at', '2026-10-05 08:30:00', 'c']] as [$column, $time, $hash]) {
            DB::table('company_invitations')->insert([
                'company_id' => $this->companyId, 'email' => "{$hash}@example.test", 'role' => 'recruiter', 'token_hash' => str_repeat($hash, 64),
                'sent_at' => '2026-10-05 08:00:00', 'expires_at' => '2026-10-12 08:00:00', $column => $time, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('company_invitations')->insert([
            'company_id' => $this->companyId, 'email' => 'old@example.test', 'role' => 'recruiter', 'token_hash' => str_repeat('d', 64),
            'sent_at' => '2026-09-01 08:00:00', 'expires_at' => '2026-09-08 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherCompany = $this->makeCompany('PT Lain', 'pt-lain');
        DB::table('company_invitations')->insert([
            'company_id' => $otherCompany, 'email' => 'other@example.test', 'role' => 'recruiter', 'token_hash' => str_repeat('e', 64),
            'sent_at' => '2026-10-05 08:00:00', 'expires_at' => '2026-10-12 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->api('GET', '/invitations')->assertOk();

        $this->assertSame(['second@example.test', 'first@example.test', 'by-platform@example.test'], array_column($response->json('data'), 'email'));
        $this->assertSame([
            'id' => $second, 'email' => 'second@example.test', 'role' => 'company_admin',
            'sent_at' => '2026-10-05T10:00:00+00:00', 'expires_at' => '2026-10-12T10:00:00+00:00', 'invited_by' => 'Andi Admin',
        ], $response->json('data.0'));
        $this->assertNull($response->json('data.2.invited_by'));
    }

    public function test_an_invitation_can_be_revoked_and_then_cannot_be_accepted(): void
    {
        $this->invite()->assertCreated();
        $token = $this->tokenFromMail();
        $id = (int) DB::table('company_invitations')->value('id');

        $this->api('DELETE', "/invitations/{$id}")->assertNoContent();

        $this->assertNotNull(DB::table('company_invitations')->value('revoked_at'));
        $this->acceptInvitation($token)->assertStatus(404);
        $this->api('GET', '/invitations')->assertOk()->assertJsonCount(0, 'data');
        $this->api('DELETE', "/invitations/{$id}")->assertNotFound();
    }

    public function test_only_a_pending_invitation_of_the_own_company_can_be_revoked(): void
    {
        $otherCompany = $this->makeCompany('PT Lain', 'pt-lain');
        $foreign = (int) DB::table('company_invitations')->insertGetId([
            'company_id' => $otherCompany, 'email' => 'other@example.test', 'role' => 'recruiter', 'token_hash' => str_repeat('e', 64),
            'sent_at' => '2026-10-05 08:00:00', 'expires_at' => '2026-10-12 08:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $accepted = (int) DB::table('company_invitations')->insertGetId([
            'company_id' => $this->companyId, 'email' => 'done@example.test', 'role' => 'recruiter', 'token_hash' => str_repeat('f', 64),
            'sent_at' => '2026-10-05 08:00:00', 'expires_at' => '2026-10-12 08:00:00', 'accepted_at' => '2026-10-05 08:30:00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api('DELETE', "/invitations/{$foreign}")->assertNotFound();
        $this->api('DELETE', "/invitations/{$accepted}")->assertNotFound();

        $this->assertNull(DB::table('company_invitations')->where('id', $foreign)->value('revoked_at'));
        $this->assertNull(DB::table('company_invitations')->where('id', $accepted)->value('revoked_at'));
        $this->assertSame('2026-10-05 08:30:00', DB::table('company_invitations')->where('id', $accepted)->value('accepted_at'));
    }

    // ----- members -----

    public function test_the_member_list_has_company_admins_first_then_by_name_and_only_the_own_company(): void
    {
        $this->makeMember($this->companyId, 'Bambang Rekruter', 'bambang@example.test', 'recruiter');
        $this->makeMember($this->companyId, 'Cici Admin', 'cici@example.test', 'company_admin');
        $this->makeMember($this->makeCompany('PT Lain', 'pt-lain'), 'Oki Lain', 'oki@example.test', 'company_admin');
        $this->admin->forceFill(['last_login_at' => '2026-10-04 07:00:00'])->save();

        $response = $this->api('GET', '/members')->assertOk();

        $this->assertSame(['Andi Admin', 'Cici Admin', 'Bambang Rekruter', 'Rina Rekruter'], array_column($response->json('data'), 'name'));
        $this->assertSame([
            'user_id' => $this->admin->id, 'name' => 'Andi Admin', 'email' => 'andi@example.test', 'role' => 'company_admin', 'status' => 'active',
            'joined_at' => '2026-10-05T09:00:00+00:00', 'last_login_at' => '2026-10-04T07:00:00+00:00',
        ], $response->json('data.0'));
        $this->assertNull($response->json('data.3.last_login_at'));
    }

    // ----- removing -----

    public function test_removing_a_member_releases_the_membership_and_deactivates_the_account(): void
    {
        $this->api('DELETE', "/members/{$this->recruiter->id}")->assertNoContent();

        $this->assertFalse(DB::table('company_members')->where('user_id', $this->recruiter->id)->exists());
        $account = UserModel::query()->findOrFail($this->recruiter->id);
        $this->assertSame('deactivated', $account->status);
        $this->assertSame('Rina Rekruter', $account->name);
        $this->assertNull($account->anonymized_at);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->recruiter->id)->count());

        $this->api('GET', '/members', [], $this->recruiterToken)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'rina@example.test', 'password' => self::PASSWORD])
            ->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:account-deactivated');
        $this->invite('rina@example.test')->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:email-already-registered');
    }

    public function test_a_company_admin_can_remove_another_company_admin_and_themselves_while_one_remains(): void
    {
        $second = $this->makeMember($this->companyId, 'Cici Admin', 'cici@example.test', 'company_admin');

        $this->api('DELETE', "/members/{$second->id}")->assertNoContent();
        $this->assertSame('deactivated', UserModel::query()->findOrFail($second->id)->status);

        $third = $this->makeMember($this->companyId, 'Dedi Admin', 'dedi@example.test', 'company_admin');
        $this->api('DELETE', "/members/{$this->admin->id}")->assertNoContent();

        $remaining = DB::table('company_members')->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
        $this->assertEqualsCanonicalizing([$third->id, $this->recruiter->id], $remaining);
    }

    public function test_the_last_company_admin_cannot_be_removed(): void
    {
        $this->api('DELETE', "/members/{$this->admin->id}")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:last-company-admin');

        $this->assertTrue(DB::table('company_members')->where('user_id', $this->admin->id)->exists());
        $this->assertSame('active', UserModel::query()->findOrFail($this->admin->id)->status);
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $this->admin->id)->count());
    }

    public function test_only_a_member_of_the_own_company_can_be_removed(): void
    {
        $outsider = $this->makeMember($this->makeCompany('PT Lain', 'pt-lain'), 'Oki Lain', 'oki@example.test', 'recruiter');

        $this->api('DELETE', "/members/{$outsider->id}")->assertNotFound();
        $this->api('DELETE', '/members/999999')->assertNotFound();

        $this->assertSame('active', UserModel::query()->findOrFail($outsider->id)->status);
        $this->assertTrue(DB::table('company_members')->where('user_id', $outsider->id)->exists());
    }

    // ----- changing roles -----

    public function test_a_recruiter_can_be_promoted_and_a_company_admin_demoted(): void
    {
        $this->api('PUT', "/members/{$this->recruiter->id}/role", ['role' => 'company_admin'])->assertNoContent();
        $this->assertSame('company_admin', DB::table('company_members')->where('user_id', $this->recruiter->id)->value('role'));

        $this->api('PUT', "/members/{$this->admin->id}/role", ['role' => 'recruiter'])->assertNoContent();
        $this->assertSame('recruiter', DB::table('company_members')->where('user_id', $this->admin->id)->value('role'));

        // The demoted admin lost the right with the next request.
        $this->api('GET', '/members')->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-admin-required');
    }

    public function test_the_last_company_admin_cannot_be_demoted(): void
    {
        $this->api('PUT', "/members/{$this->admin->id}/role", ['role' => 'recruiter'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:last-company-admin');

        $this->assertSame('company_admin', DB::table('company_members')->where('user_id', $this->admin->id)->value('role'));
    }

    public function test_giving_the_same_role_changes_and_audits_nothing(): void
    {
        $this->api('PUT', "/members/{$this->recruiter->id}/role", ['role' => 'recruiter'])->assertNoContent();
        $this->api('PUT', "/members/{$this->admin->id}/role", ['role' => 'company_admin'])->assertNoContent();

        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'company.member_role_changed')->count());
    }

    public function test_a_role_change_needs_a_valid_role_and_a_member_of_the_own_company(): void
    {
        $outsider = $this->makeMember($this->makeCompany('PT Lain', 'pt-lain'), 'Oki Lain', 'oki@example.test', 'recruiter');

        $this->api('PUT', "/members/{$this->recruiter->id}/role", ['role' => 'owner'])->assertStatus(422);
        $this->api('PUT', "/members/{$this->recruiter->id}/role", [])->assertStatus(422);
        $this->api('PUT', "/members/{$outsider->id}/role", ['role' => 'company_admin'])->assertNotFound();

        $this->assertSame('recruiter', DB::table('company_members')->where('user_id', $outsider->id)->value('role'));
    }

    // ----- audit -----

    public function test_removals_and_role_changes_are_audited_with_the_actor_and_without_email_addresses(): void
    {
        $this->api('PUT', "/members/{$this->recruiter->id}/role", ['role' => 'company_admin'])->assertNoContent();
        $this->api('DELETE', "/members/{$this->recruiter->id}")->assertNoContent();

        $changed = DB::table('platform_audit_logs')->where('action', 'company.member_role_changed')->first();
        $removed = DB::table('platform_audit_logs')->where('action', 'company.member_removed')->first();

        $this->assertSame('user', $changed->actor_type);
        $this->assertSame($this->admin->id, (int) $changed->actor_id);
        $this->assertSame('company', $changed->subject_type);
        $this->assertSame($this->companyId, (int) $changed->subject_id);
        $this->assertSame(['user_id' => $this->recruiter->id, 'old_role' => 'recruiter', 'new_role' => 'company_admin'], json_decode((string) $changed->metadata, true));
        $this->assertSame($this->admin->id, (int) $removed->actor_id);
        $this->assertSame(['user_id' => $this->recruiter->id, 'role' => 'company_admin'], json_decode((string) $removed->metadata, true));
        $this->assertStringNotContainsString('example.test', json_encode(DB::table('platform_audit_logs')->get()->all()));
    }

    public function test_sending_and_revoking_an_invitation_are_audited_without_the_email_address(): void
    {
        $this->invite('new@example.test', 'recruiter')->assertCreated();
        $id = (int) DB::table('company_invitations')->value('id');
        $this->api('DELETE', "/invitations/{$id}")->assertNoContent();

        $sent = DB::table('platform_audit_logs')->where('action', 'company.invitation_sent')->first();
        $revoked = DB::table('platform_audit_logs')->where('action', 'company.invitation_revoked')->first();

        $this->assertSame('user', $sent->actor_type);
        $this->assertSame($this->admin->id, (int) $sent->actor_id);
        $this->assertSame('company', $sent->subject_type);
        $this->assertSame($this->companyId, (int) $sent->subject_id);
        $this->assertSame(['invitation_id' => $id, 'role' => 'recruiter'], json_decode((string) $sent->metadata, true));
        $this->assertNotNull($sent->ip_address);

        $this->assertSame($this->admin->id, (int) $revoked->actor_id);
        $this->assertSame(['invitation_id' => $id], json_decode((string) $revoked->metadata, true));
        $this->assertStringNotContainsString('example.test', json_encode(DB::table('platform_audit_logs')->get()->all()));
    }

    public function test_a_replaced_invitation_is_audited_as_a_new_invitation_sent(): void
    {
        $this->invite()->assertCreated();
        Carbon::setTestNow('2026-10-05 09:02:00');
        $this->invite()->assertCreated();

        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'company.invitation_sent')->count());
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'company.invitation_revoked')->count());
    }

    public function test_a_refused_invitation_leaves_no_audit_entry(): void
    {
        $this->invite('rina@example.test')->assertStatus(409);
        $this->invite('new@example.test', 'owner')->assertStatus(422);
        $this->api('POST', '/invitations', ['email' => 'x@example.test', 'role' => 'recruiter'], $this->recruiterToken)->assertStatus(403);

        $this->assertSame(0, DB::table('platform_audit_logs')->count());
    }

    // ----- platform admin invites a company admin again -----

    public function test_a_platform_admin_can_invite_a_company_admin_again(): void
    {
        $token = $this->platformAdminToken();
        $this->app['auth']->forgetGuards();

        $response = $this->withToken($token)->postJson("/api/v1/admin/companies/{$this->companyId}/invitations", ['email' => 'Fresh@Example.test'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'fresh@example.test')
            ->assertJsonPath('data.role', 'company_admin')
            ->assertJsonPath('data.expires_at', '2026-10-12T09:00:00+00:00');
        $this->assertArrayNotHasKey('token', $response->json('data'));

        $row = DB::table('company_invitations')->first();
        $this->assertNull($row->invited_by_user_id);
        $this->assertNotNull($row->invited_by_admin_id);

        $sent = DB::table('platform_audit_logs')->where('action', 'company.invitation_sent')->first();
        $this->assertSame('admin', $sent->actor_type);
        $this->assertSame((int) $row->invited_by_admin_id, (int) $sent->actor_id);
        $this->assertSame(['invitation_id' => (int) $row->id, 'role' => 'company_admin'], json_decode((string) $sent->metadata, true));

        $this->acceptInvitation($this->tokenFromMail())->assertCreated();
        $this->assertSame('company_admin', DB::table('company_members')->orderByDesc('id')->value('role'));
    }

    public function test_the_platform_admin_invitation_needs_the_permission_and_a_known_company_and_a_free_email(): void
    {
        $this->postJson("/api/v1/admin/companies/{$this->companyId}/invitations", ['email' => 'fresh@example.test'])->assertStatus(401);

        $weak = $this->platformAdminToken(['faqs.manage'], 'weak@example.test');
        $this->app['auth']->forgetGuards();
        $this->withToken($weak)->postJson("/api/v1/admin/companies/{$this->companyId}/invitations", ['email' => 'fresh@example.test'])->assertStatus(403);

        $token = $this->platformAdminToken(['companies.manage'], 'strong@example.test');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/admin/companies/999999/invitations', ['email' => 'fresh@example.test'])->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/admin/companies/{$this->companyId}/invitations", ['email' => 'andi@example.test'])->assertStatus(409);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/admin/companies/{$this->companyId}/invitations", ['email' => 'bad'])->assertStatus(422);

        $this->assertSame(0, DB::table('company_invitations')->count());
    }

    public function test_the_platform_admin_invitation_replaces_an_expired_or_pending_one_with_the_same_cooldown(): void
    {
        $token = $this->platformAdminToken();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/admin/companies/{$this->companyId}/invitations", ['email' => 'fresh@example.test'])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/admin/companies/{$this->companyId}/invitations", ['email' => 'fresh@example.test'])->assertStatus(429);

        Carbon::setTestNow('2026-10-20 09:00:00');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/admin/companies/{$this->companyId}/invitations", ['email' => 'fresh@example.test'])->assertCreated();

        $this->acceptInvitation($this->tokenFromMail(1))->assertCreated();
    }
}
