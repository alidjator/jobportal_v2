<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Application\Account\AccountNoticeSender;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;
use Tests\Support\RecordingAccountNoticeSender;
use Tests\Support\RecordingVerificationCodeSender;

/** `docs/design/phase-8-activation.md` sections 7 and 8: what an admin holding `users.manage` does for a locked-out job seeker or company member. */
final class AdminUserActionsTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string REASON = 'Lost the old mailbox, asked by phone';

    private const string CHECK = 'Registered phone number answered; last application title and date matched';

    private RecordingAccountNoticeSender $notices;

    private RecordingVerificationCodeSender $codes;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->notices = new RecordingAccountNoticeSender;
        $this->codes = new RecordingVerificationCodeSender;
        $this->app->instance(AccountNoticeSender::class, $this->notices);
        $this->app->instance(VerificationCodeSender::class, $this->codes);

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->token = $this->adminToken(['users.view', 'users.manage']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'ops@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    /** @param  array<string, mixed>  $attributes */
    private function user(string $email = 'siti@example.test', array $attributes = []): UserModel
    {
        return UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti Rahma', 'email' => $email, 'password' => Hash::make('correct-horse-battery'),
            'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00', ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/admin'.$uri, $body);
    }

    /** @return array<string, string> */
    private function proof(): array
    {
        return ['reason' => self::REASON, 'identity_check' => self::CHECK];
    }

    private function tokenCount(UserModel $user): int
    {
        return $user->tokens()->count();
    }

    private function company(?array $domains = null): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Maju', 'slug' => 'pt-maju', 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'invitation_email_domains' => $domains === null ? null : json_encode($domains), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_email_is_corrected_and_the_account_starts_over(): void
    {
        $user = $this->user();
        $user->createToken('phone', ['*'], now()->addDay());
        $user->createToken('laptop', ['*'], now()->addDay());
        DB::table('identity_email_changes')->insert([
            'user_id' => $user->id, 'new_email' => 'pending@example.test', 'new_code_hash' => 'x', 'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'created_at' => now(),
        ]);

        $this->admin('PUT', "/users/{$user->id}/email", ['email' => 'Baru@Example.test', ...$this->proof()])->assertNoContent();

        $user->refresh();
        $this->assertSame('baru@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(0, $this->tokenCount($user));
        $this->assertSame(0, DB::table('identity_email_changes')->count());

        $old = $this->notices->of('corrected_old');
        $this->assertSame(['siti@example.test', 'baru@example.test'], [$old[0]['to'], $old[0]['newEmail']]);
        $new = $this->notices->of('corrected_new');
        $this->assertSame(['baru@example.test', 'job_seeker'], [$new[0]['to'], $new[0]['audience']]);

        $entry = DB::table('platform_audit_logs')->where('action', 'account.email_corrected')->first();
        $this->assertSame(['admin', 'user', $user->id], [$entry->actor_type, $entry->subject_type, (int) $entry->subject_id]);
        $this->assertEquals([
            'reason' => self::REASON, 'identity_check' => self::CHECK, 'old_email' => 's***@example.test', 'new_email' => 'b***@example.test',
        ], json_decode($entry->metadata, true));
    }

    public function test_the_company_domain_limit_does_not_apply_to_a_correction(): void
    {
        $companyId = $this->company(['example.test']);
        $member = $this->user('andi@example.test', ['type' => 'company_member']);
        CompanyMemberRows::insert($companyId, $member->id, 'company_admin');

        $this->admin('PUT', "/users/{$member->id}/email", ['email' => 'andi@elsewhere.test', ...$this->proof()])->assertNoContent();

        $this->assertSame('andi@elsewhere.test', $member->refresh()->email);
        $this->assertSame('company_member', $this->notices->of('corrected_new')[0]['audience']);
    }

    public function test_a_correction_needs_a_reason_the_proofs_and_a_free_deliverable_address(): void
    {
        $user = $this->user();
        $this->user('taken@example.test');
        DB::table('platform_email_suppressions')->insert(['email' => 'bounced@example.test', 'reason' => 'hard_bounce', 'source' => 'test', 'created_at' => now()]);

        $this->admin('PUT', "/users/{$user->id}/email", ['email' => 'baru@example.test'])->assertStatus(422)->assertJsonValidationErrors(['reason', 'identity_check']);
        $this->admin('PUT', "/users/{$user->id}/email", ['email' => 'baru@example.test', 'reason' => 'x', 'identity_check' => 'y'])->assertStatus(422);
        $this->admin('PUT', "/users/{$user->id}/email", ['email' => 'nope', ...$this->proof()])->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->admin('PUT', "/users/{$user->id}/email", ['email' => 'taken@example.test', ...$this->proof()])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:email-in-use');
        $this->admin('PUT', "/users/{$user->id}/email", ['email' => 'bounced@example.test', ...$this->proof()])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-undeliverable');

        $this->assertSame('siti@example.test', $user->refresh()->email);
        $this->assertSame([], $this->notices->sent);
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'account.email_corrected')->count());
    }

    public function test_a_psychologist_or_a_missing_or_anonymised_account_is_not_correctable(): void
    {
        $psychologist = $this->user('psy@example.test', ['type' => 'psychologist']);
        $gone = $this->user('gone@example.test', ['anonymized_at' => now()]);

        $this->admin('PUT', "/users/{$psychologist->id}/email", ['email' => 'baru@example.test', ...$this->proof()])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:account-not-correctable');
        $this->admin('POST', "/users/{$psychologist->id}/reset-two-factor", $this->proof())->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:account-not-correctable');
        $this->admin('PUT', "/users/{$gone->id}/email", ['email' => 'baru@example.test', ...$this->proof()])->assertNotFound();
        $this->admin('PUT', '/users/999999/email', ['email' => 'baru@example.test', ...$this->proof()])->assertNotFound();
    }

    public function test_the_actions_need_the_manage_permission(): void
    {
        $user = $this->user();
        $viewer = $this->adminToken(['users.view'], 'viewer@example.test');

        $this->admin('PUT', "/users/{$user->id}/email", ['email' => 'baru@example.test', ...$this->proof()], $viewer)->assertForbidden();
        $this->admin('POST', "/users/{$user->id}/reset-two-factor", $this->proof(), $viewer)->assertForbidden();
        $this->admin('POST', "/users/{$user->id}/activation-email", [], $viewer)->assertForbidden();
        $this->admin('POST', "/users/{$user->id}/password-reset", [], $viewer)->assertForbidden();
        $this->admin('POST', "/users/{$user->id}/resend-verification", [], $viewer)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->postJson("/api/v1/admin/users/{$user->id}/password-reset")->assertUnauthorized();
    }

    public function test_the_second_factor_of_a_company_member_is_reset(): void
    {
        $companyId = $this->company();
        $member = $this->user('andi@example.test', ['type' => 'company_member', 'name' => 'Andi']);
        CompanyMemberRows::insert($companyId, $member->id, 'company_admin');
        $member->createToken('phone', ['*'], now()->addDay());
        DB::table('identity_user_two_factor')->insert(['user_id' => $member->id, 'secret' => 'secret', 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('identity_user_recovery_codes')->insert(['user_id' => $member->id, 'code_hash' => str_repeat('a', 64)]);

        $this->admin('POST', "/users/{$member->id}/reset-two-factor", $this->proof())->assertNoContent();

        $this->assertSame(0, DB::table('identity_user_two_factor')->where('user_id', $member->id)->count());
        $this->assertSame(0, DB::table('identity_user_recovery_codes')->where('user_id', $member->id)->count());
        $this->assertSame(0, $this->tokenCount($member));
        $this->assertSame(['andi@example.test'], array_column($this->notices->of('two_factor_reset'), 'to'));
        $this->assertSame(1, DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $member->id)->where('type', 'company.two_factor_reset')->count());

        $entry = DB::table('platform_audit_logs')->where('action', 'account.two_factor_reset')->first();
        $this->assertSame(['admin', 'user', $member->id], [$entry->actor_type, $entry->subject_type, (int) $entry->subject_id]);
        $this->assertEquals(['reason' => self::REASON, 'identity_check' => self::CHECK], json_decode($entry->metadata, true));
    }

    public function test_the_second_factor_of_a_job_seeker_is_reset_without_a_company_notice(): void
    {
        $user = $this->user();
        $user->createToken('phone', ['*'], now()->addDay());
        DB::table('identity_user_two_factor')->insert(['user_id' => $user->id, 'secret' => 'secret', 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->admin('POST', "/users/{$user->id}/reset-two-factor", $this->proof())->assertNoContent();

        $this->assertSame(0, DB::table('identity_user_two_factor')->count());
        $this->assertSame(0, $this->tokenCount($user));
        $this->assertSame(1, count($this->notices->of('two_factor_reset')));
        $this->assertSame(0, DB::table('notifications')->where('type', 'company.two_factor_reset')->count());
    }

    public function test_a_reset_needs_a_reason_and_the_proofs(): void
    {
        $user = $this->user();

        $this->admin('POST', "/users/{$user->id}/reset-two-factor", [])->assertStatus(422)->assertJsonValidationErrors(['reason', 'identity_check']);
    }

    public function test_the_detail_tells_when_the_account_is_the_only_company_admin(): void
    {
        $companyId = $this->company();
        $owner = $this->user('andi@example.test', ['type' => 'company_member', 'name' => 'Andi']);
        $second = $this->user('cici@example.test', ['type' => 'company_member', 'name' => 'Cici']);
        $recruiter = $this->user('rina@example.test', ['type' => 'company_member', 'name' => 'Rina']);
        CompanyMemberRows::insert($companyId, $owner->id, 'company_admin');
        CompanyMemberRows::insert($companyId, $recruiter->id, 'recruiter');
        $seeker = $this->user();

        $this->admin('GET', "/users/{$owner->id}")->assertOk()->assertJsonPath('data.only_company_admin', true);
        $this->admin('GET', "/users/{$recruiter->id}")->assertOk()->assertJsonPath('data.only_company_admin', false);
        $this->assertArrayNotHasKey('only_company_admin', $this->admin('GET', "/users/{$seeker->id}")->assertOk()->json('data'));
        $this->assertArrayNotHasKey('only_company_admin', $this->admin('GET', '/users')->assertOk()->json('data.0'));

        CompanyMemberRows::insert($companyId, $second->id, 'company_admin');
        $this->admin('GET', "/users/{$owner->id}")->assertOk()->assertJsonPath('data.only_company_admin', false);
    }

    public function test_the_activation_email_is_sent_again_to_an_imported_account(): void
    {
        config(['app.job_seeker_activation_url' => 'https://careers.test/activate', 'app.job_seeker_sign_in_url' => 'https://careers.test/sign-in']);
        $this->app->make(SettingsStore::class)->setGlobal('platform.support_email', 'help@example.test', null);
        $user = $this->user('siti@example.test', ['imported_at' => '2026-10-01 00:00:00']);

        $this->admin('POST', "/users/{$user->id}/activation-email")->assertNoContent();
        $this->admin('POST', "/users/{$user->id}/activation-email")->assertNoContent();

        $this->assertSame(2, DB::table('identity_activation_emails')->where('user_id', $user->id)->where('kind', 'manual')->count());
        $this->assertSame(0, DB::table('identity_activation_emails')->whereIn('kind', ['activation', 'reminder'])->count());
        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'account.activation_email_sent')->where('subject_id', $user->id)->count());
    }

    public function test_the_activation_email_is_refused_for_an_account_that_was_not_imported_or_is_not_active_or_while_settings_are_empty(): void
    {
        $native = $this->user('native@example.test');
        $suspended = $this->user('suspended@example.test', ['imported_at' => '2026-10-01 00:00:00', 'status' => 'suspended']);
        $imported = $this->user('imported@example.test', ['imported_at' => '2026-10-01 00:00:00']);

        $this->admin('POST', "/users/{$native->id}/activation-email")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:account-not-imported');
        $this->admin('POST', "/users/{$suspended->id}/activation-email")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:account-not-active');
        $this->admin('POST', "/users/{$imported->id}/activation-email")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:activation-support-contact-missing');

        $this->assertSame(0, DB::table('identity_activation_emails')->count());
    }

    public function test_a_password_reset_code_goes_to_the_account_and_is_audited(): void
    {
        $user = $this->user();

        $this->admin('POST', "/users/{$user->id}/password-reset")->assertNoContent();

        $this->assertSame(['password_reset', 'siti@example.test'], [$this->codes->sent[0]['kind'], $this->codes->sent[0]['email']]);
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'account.password_reset_sent')->where('subject_id', $user->id)->count());
    }

    public function test_a_password_reset_is_refused_for_an_account_that_is_not_active(): void
    {
        $user = $this->user('suspended@example.test', ['status' => 'suspended']);

        $this->admin('POST', "/users/{$user->id}/password-reset")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:account-not-active');
        $this->admin('POST', "/users/{$user->id}/resend-verification")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:account-not-active');

        $this->assertSame([], $this->codes->sent);
    }

    public function test_the_verification_code_is_sent_again_only_to_an_unverified_account(): void
    {
        $unverified = $this->user('new@example.test', ['email_verified_at' => null]);
        $verified = $this->user();

        $this->admin('POST', "/users/{$unverified->id}/resend-verification")->assertNoContent();
        $this->admin('POST', "/users/{$verified->id}/resend-verification")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:email-already-verified');

        $this->assertSame(['email_verification', 'new@example.test'], [$this->codes->sent[0]['kind'], $this->codes->sent[0]['email']]);
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'account.verification_resent')->where('subject_id', $unverified->id)->count());
    }

    public function test_the_support_actions_are_limited_per_account(): void
    {
        config(['app.job_seeker_activation_url' => 'https://careers.test/activate', 'app.job_seeker_sign_in_url' => 'https://careers.test/sign-in']);
        $this->app->make(SettingsStore::class)->setGlobal('platform.support_email', 'help@example.test', null);
        $user = $this->user('siti@example.test', ['imported_at' => '2026-10-01 00:00:00']);

        for ($count = 1; $count <= 5; $count++) {
            $this->admin('POST', "/users/{$user->id}/activation-email")->assertNoContent();
        }

        $this->admin('POST', "/users/{$user->id}/activation-email")->assertStatus(429);
    }
}
