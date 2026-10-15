<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Application\Auth\GoogleIdentityVerifier;
use Src\Identity\Infrastructure\Mail\AccountNoticeMail;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;
use Tests\Support\FakeGoogleIdentityVerifier;

/** `docs/design/phase-8-company-admin.md` section 1: the second factor of company members (required of company admins and of a company that asks for it). */
final class CompanyTwoFactorTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string PASSWORD = 'password-123';

    private int $companyId;

    private UserModel $owner;

    private UserModel $second;

    private UserModel $recruiter;

    private string $ownerToken;

    private string $recruiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        Mail::fake();

        $this->companyId = (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Maju Bersama', 'slug' => 'pt-maju-bersama', 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->owner = $this->member('Andi Admin', 'andi@example.test', 'company_admin');
        $this->second = $this->member('Cici Admin', 'cici@example.test', 'company_admin');
        $this->recruiter = $this->member('Rina Rekruter', 'rina@example.test', 'recruiter');
        $this->ownerToken = $this->tokenOf($this->owner);
        $this->recruiterToken = $this->tokenOf($this->recruiter);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(string $name, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($this->companyId, $user->id, $role);

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

        return $this->withToken($token ?? $this->ownerToken)->json($method, '/api/v1'.$uri, $body);
    }

    private function login(string $email): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
    }

    /** A code the secret accepts now, even when a code was already used in this 30-second step. */
    private function code(string $secret, int $userId): string
    {
        DB::table('identity_user_two_factor')->where('user_id', $userId)->update(['last_step' => null]);
        $google2fa = new Google2FA;

        return $google2fa->oathTotp($secret, $google2fa->getTimestamp());
    }

    private function finish(TestResponse $challenge, string $code): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders()->postJson('/api/v1/auth/two-factor', ['challenge_token' => $challenge->json('data.challenge_token'), 'code' => $code]);
    }

    /** Switches the second factor on for a member through the account endpoints. @return array{string, list<string>} the secret and the recovery codes */
    private function enrolled(UserModel $user, ?string $token = null): array
    {
        $token ??= $this->tokenOf($user);
        $secret = (string) $this->api('POST', '/me/two-factor', [], $token)->assertOk()->json('data.secret');
        $codes = $this->api('POST', '/me/two-factor/confirm', ['code' => $this->code($secret, $user->id)], $token)->assertOk()->json('data.recovery_codes');

        return [$secret, $codes];
    }

    private function tokenCount(UserModel $user): int
    {
        return DB::table('personal_access_tokens')->where('tokenable_type', 'user')->where('tokenable_id', $user->id)->count();
    }

    private function requireForCompany(bool $on = true): void
    {
        $this->api('PATCH', '/company/settings', ['company.require_two_factor' => $on])->assertOk();
    }

    // ----- sign-in -----

    public function test_a_company_admin_enrols_at_the_first_sign_in_and_gets_a_token_only_after_the_code(): void
    {
        $challenge = $this->login('cici@example.test')->assertOk()
            ->assertJsonPath('data.status', 'two_factor_enrolment_required')
            ->assertJsonPath('data.expires_at', '2026-10-05T09:05:00+00:00')
            ->assertJsonStructure(['data' => ['challenge_token', 'enrolment' => ['secret', 'otpauth_url', 'qr_code']]])
            ->assertJsonMissingPath('data.token');
        $this->assertSame(0, $this->tokenCount($this->second));

        $done = $this->finish($challenge, $this->code($challenge->json('data.enrolment.secret'), $this->second->id))->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', '2026-11-04T09:00:00+00:00')
            ->assertJsonPath('data.user.email', 'cici@example.test')
            ->assertJsonCount(10, 'data.recovery_codes');

        $this->api('GET', '/company/me', [], $done->json('data.token'))->assertOk()->assertJsonPath('data.role', 'company_admin');
        $this->assertNotNull(DB::table('identity_user_two_factor')->where('user_id', $this->second->id)->value('confirmed_at'));
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.two_factor_enabled', 'actor_id' => $this->second->id, 'company_id' => $this->companyId]);
    }

    public function test_a_later_sign_in_asks_for_a_code_and_a_recovery_code_works_once(): void
    {
        $first = $this->login('cici@example.test');
        $secret = $first->json('data.enrolment.secret');
        $codes = $this->finish($first, $this->code($secret, $this->second->id))->assertOk()->json('data.recovery_codes');

        $second = $this->login('cici@example.test')->assertOk()
            ->assertJsonPath('data.status', 'two_factor_required')
            ->assertJsonMissingPath('data.enrolment')
            ->assertJsonMissingPath('data.token');

        $this->finish($second, '000000')->assertUnauthorized()->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
        $this->finish($second, $this->code($secret, $this->second->id))->assertOk()->assertJsonMissingPath('data.recovery_codes');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->postJson('/api/v1/auth/two-factor', ['challenge_token' => $this->login('cici@example.test')->json('data.challenge_token'), 'recovery_code' => $codes[0]])->assertOk();
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.recovery_code_used', 'actor_id' => $this->second->id]);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->postJson('/api/v1/auth/two-factor', ['challenge_token' => $this->login('cici@example.test')->json('data.challenge_token'), 'recovery_code' => $codes[0]])
            ->assertUnauthorized()->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
    }

    public function test_a_member_may_do_without_a_second_factor_and_a_job_seeker_never_has_one(): void
    {
        $this->login('rina@example.test')->assertOk()->assertJsonPath('data.token_type', 'Bearer')->assertJsonPath('data.user.type', 'company_member');

        UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'password' => Hash::make(self::PASSWORD), 'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00']);
        $this->login('siti@example.test')->assertOk()->assertJsonPath('data.user.type', 'job_seeker');
    }

    public function test_a_member_with_a_confirmed_factor_needs_the_code_and_one_of_a_requiring_company_needs_to_enrol(): void
    {
        [$secret] = $this->enrolled($this->recruiter, $this->recruiterToken);

        $challenge = $this->login('rina@example.test')->assertOk()->assertJsonPath('data.status', 'two_factor_required');
        $this->finish($challenge, $this->code($secret, $this->recruiter->id))->assertOk();

        $other = $this->member('Dodi Developer', 'dodi@example.test', 'recruiter');
        $this->login('dodi@example.test')->assertOk()->assertJsonPath('data.token_type', 'Bearer');

        $this->requireForCompany();
        $this->login('dodi@example.test')->assertOk()->assertJsonPath('data.status', 'two_factor_enrolment_required')->assertJsonMissingPath('data.token');
        $this->assertSame(0, $this->tokenCount($other));
    }

    public function test_a_suspended_member_is_refused_before_the_second_factor(): void
    {
        $this->api('POST', "/company/members/{$this->recruiter->id}/suspend")->assertNoContent();

        $this->login('rina@example.test')->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:membership-suspended');
    }

    public function test_google_sign_in_of_a_company_admin_also_asks_for_the_second_factor(): void
    {
        $google = new FakeGoogleIdentityVerifier;
        $google->accept('token-cici', 'google-subject-9', 'cici@example.test');
        $this->app->instance(GoogleIdentityVerifier::class, $google);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->postJson('/api/v1/auth/google', ['id_token' => 'token-cici'])->assertOk()
            ->assertJsonPath('data.status', 'two_factor_enrolment_required')
            ->assertJsonMissingPath('data.token');
        $this->assertSame(0, $this->tokenCount($this->second));
    }

    public function test_an_unknown_challenge_is_refused(): void
    {
        $this->app['auth']->forgetGuards();

        $this->flushHeaders()->postJson('/api/v1/auth/two-factor', ['challenge_token' => str_repeat('a', 43), 'code' => '123456'])
            ->assertUnauthorized()->assertJsonPath('type', 'urn:jobportal:error:two-factor-challenge-expired');
    }

    public function test_the_challenge_of_a_psychologist_does_not_finish_a_company_member_sign_in(): void
    {
        $psychologist = UserModel::query()->create(['type' => 'psychologist', 'name' => 'Dewi', 'email' => 'dewi@example.test', 'password' => Hash::make(self::PASSWORD), 'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00']);
        $this->app['auth']->forgetGuards();
        $challenge = $this->flushHeaders()->postJson('/api/v1/provider/auth/login', ['email' => 'dewi@example.test', 'password' => self::PASSWORD])->assertOk();

        $this->finish($challenge, $this->code($challenge->json('data.enrolment.secret'), $psychologist->id))
            ->assertUnauthorized()->assertJsonPath('type', 'urn:jobportal:error:two-factor-challenge-expired');
    }

    // ----- the account endpoints -----

    public function test_only_a_company_member_has_a_second_factor_to_manage(): void
    {
        $seeker = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'id']);

        $this->api('GET', '/me/two-factor', [], $this->tokenOf($seeker))->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
        $this->api('POST', '/me/two-factor', [], $this->tokenOf($seeker))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/me/two-factor')->assertUnauthorized();
    }

    public function test_a_member_switches_the_second_factor_on_renews_the_recovery_codes_and_switches_it_off(): void
    {
        $token = $this->recruiterToken;
        $this->api('GET', '/me/two-factor', [], $token)->assertOk()->assertExactJson(['data' => ['enabled' => false, 'required' => false, 'recovery_codes_left' => 0]]);

        $secret = (string) $this->api('POST', '/me/two-factor', [], $token)->assertOk()->assertJsonStructure(['data' => ['secret', 'otpauth_url', 'qr_code']])->json('data.secret');
        $this->assertNotSame($secret, DB::table('identity_user_two_factor')->where('user_id', $this->recruiter->id)->value('secret'), 'the secret is stored encrypted');

        $this->api('POST', '/me/two-factor/confirm', ['code' => '000000'], $token)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
        $codes = $this->api('POST', '/me/two-factor/confirm', ['code' => $this->code($secret, $this->recruiter->id)], $token)->assertOk()->assertJsonCount(10, 'data.recovery_codes')->json('data.recovery_codes');
        $this->assertCount(10, array_unique($codes));
        $this->api('POST', '/me/two-factor/confirm', ['code' => $this->code($secret, $this->recruiter->id)], $token)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:two-factor-not-started');
        $this->api('POST', '/me/two-factor', [], $token)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:two-factor-already-enabled');
        $this->api('GET', '/me/two-factor', [], $token)->assertOk()->assertExactJson(['data' => ['enabled' => true, 'required' => false, 'recovery_codes_left' => 10]]);

        $this->api('POST', '/me/two-factor/recovery-codes', ['code' => '000000'], $token)->assertStatus(422);
        $renewed = $this->api('POST', '/me/two-factor/recovery-codes', ['code' => $this->code($secret, $this->recruiter->id)], $token)->assertOk()->assertJsonCount(10, 'data.recovery_codes')->json('data.recovery_codes');
        $this->assertEmpty(array_intersect($codes, $renewed));

        $this->api('DELETE', '/me/two-factor', ['password' => 'wrong-password', 'code' => $this->code($secret, $this->recruiter->id)], $token)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-password');
        $this->api('DELETE', '/me/two-factor', ['password' => self::PASSWORD, 'code' => $this->code($secret, $this->recruiter->id)], $token)->assertNoContent();
        $this->assertSame(0, DB::table('identity_user_two_factor')->where('user_id', $this->recruiter->id)->count());
        $this->assertSame(0, DB::table('identity_user_recovery_codes')->where('user_id', $this->recruiter->id)->count());
        $this->api('GET', '/me/two-factor', [], $token)->assertOk()->assertJsonPath('data.enabled', false);

        $actions = DB::table('platform_audit_logs')->where('actor_id', $this->recruiter->id)->pluck('action')->all();
        $this->assertContains('auth.two_factor_enabled', $actions);
        $this->assertContains('auth.recovery_codes_regenerated', $actions);
        $this->assertContains('auth.two_factor_disabled', $actions);
    }

    public function test_a_company_admin_cannot_switch_the_second_factor_off(): void
    {
        [$secret] = $this->enrolled($this->owner, $this->ownerToken);

        $this->api('GET', '/me/two-factor')->assertOk()->assertJsonPath('data.required', true)->assertJsonPath('data.enabled', true);
        $this->api('DELETE', '/me/two-factor', ['password' => self::PASSWORD, 'code' => $this->code($secret, $this->owner->id)])
            ->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:two-factor-required');
        $this->assertSame(1, DB::table('identity_user_two_factor')->where('user_id', $this->owner->id)->count());
    }

    public function test_a_member_of_a_requiring_company_cannot_switch_it_off_either(): void
    {
        [$secret] = $this->enrolled($this->recruiter, $this->recruiterToken);
        $this->requireForCompany();

        $this->api('DELETE', '/me/two-factor', ['password' => self::PASSWORD, 'code' => $this->code($secret, $this->recruiter->id)], $this->recruiterToken)
            ->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:two-factor-required');
    }

    // ----- becoming required -----

    public function test_promoting_a_member_to_company_admin_ends_the_sessions_of_one_without_a_factor(): void
    {
        $this->assertSame(1, $this->tokenCount($this->recruiter));

        $this->api('PATCH', "/company/members/{$this->recruiter->id}", ['role' => 'company_admin'])->assertNoContent();

        $this->assertSame(0, $this->tokenCount($this->recruiter));
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail): bool => $mail->hasTo('rina@example.test'));
        $this->api('GET', '/me', [], $this->recruiterToken)->assertUnauthorized();
    }

    public function test_promoting_a_member_who_already_has_a_factor_keeps_the_sessions(): void
    {
        $this->enrolled($this->recruiter, $this->recruiterToken);

        $this->api('PATCH', "/company/members/{$this->recruiter->id}", ['role' => 'company_admin'])->assertNoContent();

        $this->assertSame(1, $this->tokenCount($this->recruiter));
        Mail::assertNothingQueued();
    }

    public function test_switching_the_company_requirement_on_ends_the_sessions_of_members_without_a_factor(): void
    {
        $withFactor = $this->member('Eko Ekspert', 'eko@example.test', 'recruiter');
        $this->enrolled($withFactor);
        $bare = $this->member('Dodi Developer', 'dodi@example.test', 'recruiter');
        $this->tokenOf($bare);

        $this->requireForCompany();

        $this->assertSame(0, $this->tokenCount($this->recruiter));
        $this->assertSame(0, $this->tokenCount($bare));
        $this->assertSame(1, $this->tokenCount($withFactor), 'a member who has a factor keeps the sessions');
        $this->assertSame(1, $this->tokenCount($this->owner), 'a company admin was required already');
        Mail::assertQueued(AccountNoticeMail::class, 2);
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail): bool => $mail->hasTo('rina@example.test'));

        // Saving the same value again changes nothing and ends no one's sessions.
        $this->tokenOf($this->recruiter);
        $this->requireForCompany();
        $this->assertSame(1, $this->tokenCount($this->recruiter));
    }

    public function test_a_platform_override_of_the_requirement_ends_the_sessions_too(): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['settings.manage']);

        $this->app['auth']->forgetGuards();
        $this->withToken($admin->createToken('console', ['*'], now()->addHour())->plainTextToken)
            ->putJson("/api/v1/admin/companies/{$this->companyId}/settings/company.require_two_factor", ['value' => true])->assertOk();

        $this->assertSame(0, $this->tokenCount($this->recruiter));
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail): bool => $mail->hasTo('rina@example.test'));
    }

    // ----- reset and the team page -----

    public function test_a_company_admin_resets_the_second_factor_of_a_member_who_lost_the_device(): void
    {
        $this->enrolled($this->recruiter, $this->recruiterToken);

        $this->api('POST', "/company/members/{$this->recruiter->id}/reset-two-factor")->assertNoContent();

        $this->assertSame(0, DB::table('identity_user_two_factor')->where('user_id', $this->recruiter->id)->count());
        $this->assertSame(0, DB::table('identity_user_recovery_codes')->where('user_id', $this->recruiter->id)->count());
        $this->assertSame(0, $this->tokenCount($this->recruiter));
        $this->assertSame(1, DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $this->recruiter->id)->where('type', 'company.two_factor_reset')->count());

        $audit = DB::table('platform_audit_logs')->where('action', 'company.member_two_factor_reset')->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->owner->id, (int) $audit->actor_id);
        $this->assertSame($this->companyId, (int) $audit->company_id);
        $this->assertSame(['user_id' => $this->recruiter->id], json_decode((string) $audit->metadata, true));

        $this->login('rina@example.test')->assertOk()->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_the_reset_has_rules_about_who_may_reset_whom(): void
    {
        $this->api('POST', "/company/members/{$this->owner->id}/reset-two-factor")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:cannot-reset-self');
        $this->api('POST', "/company/members/{$this->owner->id}/reset-two-factor", [], $this->tokenOf($this->second))->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:owner-required');
        $this->api('POST', "/company/members/{$this->second->id}/reset-two-factor")->assertNoContent();
        $this->api('POST', "/company/members/{$this->owner->id}/reset-two-factor", [], $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-admin-required');
        $this->api('POST', '/company/members/999999/reset-two-factor')->assertNotFound();

        $otherCompany = (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Lain', 'slug' => 'pt-lain', 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stranger = UserModel::query()->create(['type' => 'company_member', 'name' => 'Lain', 'email' => 'lain@example.test', 'status' => 'active', 'locale' => 'en']);
        CompanyMemberRows::insert($otherCompany, $stranger->id, 'recruiter');
        $this->api('POST', "/company/members/{$stranger->id}/reset-two-factor")->assertNotFound();
    }

    public function test_the_team_list_and_the_member_detail_show_the_state_of_the_second_factor(): void
    {
        $this->enrolled($this->owner, $this->ownerToken);

        $states = fn (): array => collect($this->api('GET', '/company/members')->assertOk()->json('data'))->where('type', 'member')->pluck('two_factor', 'email')->all();

        $this->assertSame(['andi@example.test' => 'on', 'cici@example.test' => 'pending', 'rina@example.test' => 'off'], $states());
        $this->api('GET', "/company/members/{$this->recruiter->id}")->assertOk()->assertJsonPath('data.two_factor', 'off');

        $this->requireForCompany();
        $this->assertSame(['andi@example.test' => 'on', 'cici@example.test' => 'pending', 'rina@example.test' => 'pending'], $states());
        $this->api('GET', "/company/members/{$this->second->id}")->assertOk()->assertJsonPath('data.two_factor', 'pending');
    }

    public function test_the_operator_resets_the_second_factor_of_a_lone_company_admin_from_the_console(): void
    {
        $this->enrolled($this->owner, $this->ownerToken);

        $this->artisan('identity:reset-user-two-factor', ['email' => 'andi@example.test'])->expectsConfirmation('Remove the second factor and every session of andi@example.test?', 'yes')->assertExitCode(0);

        $this->assertSame(0, DB::table('identity_user_two_factor')->where('user_id', $this->owner->id)->count());
        $this->assertSame(0, $this->tokenCount($this->owner));
        $audit = DB::table('platform_audit_logs')->where('action', 'auth.two_factor_reset')->where('subject_type', 'user')->first();
        $this->assertNotNull($audit);
        $this->assertSame('system', $audit->actor_type);
        $this->assertSame($this->owner->id, (int) $audit->subject_id);

        $this->login('andi@example.test')->assertOk()->assertJsonPath('data.status', 'two_factor_enrolment_required');
    }

    public function test_the_console_reset_refuses_a_job_seeker_and_an_unknown_address(): void
    {
        UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'id']);

        $this->artisan('identity:reset-user-two-factor', ['email' => 'siti@example.test'])->expectsConfirmation('Remove the second factor and every session of siti@example.test?', 'yes')->assertExitCode(1);
        $this->artisan('identity:reset-user-two-factor', ['email' => 'nobody@example.test'])->expectsConfirmation('Remove the second factor and every session of nobody@example.test?', 'yes')->assertExitCode(1);
    }
}
