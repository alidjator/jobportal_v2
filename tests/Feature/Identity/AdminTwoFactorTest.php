<?php

namespace Tests\Feature\Identity;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\Concerns\SignsInAdmins;
use Tests\FeatureTestCase;

final class AdminTwoFactorTest extends FeatureTestCase
{
    use SignsInAdmins;

    private const string PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function login(string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson('/api/v1/admin/auth/login', ['email' => 'root@example.test', 'password' => $password]);
    }

    /** @param  array<string, mixed>  $body */
    private function twoFactor(array $body): TestResponse
    {
        return $this->postJson('/api/v1/admin/auth/two-factor', $body);
    }

    public function test_a_right_password_answers_a_challenge_and_never_a_token(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);
        $this->enrolTwoFactor($admin);

        $this->login()
            ->assertOk()
            ->assertJsonPath('data.status', 'two_factor_required')
            ->assertJsonPath('data.expires_at', '2026-10-03T10:05:00+00:00')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.enrolment');

        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_the_challenge_token_opens_no_other_endpoint(): void
    {
        $this->enrolTwoFactor($this->adminWithPassword(self::PASSWORD));
        $challenge = $this->login()->json('data.challenge_token');

        $this->withToken($challenge)->getJson('/api/v1/admin/me')->assertUnauthorized();
    }

    public function test_a_right_code_signs_the_admin_in(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);
        $secret = $this->enrolTwoFactor($admin);

        $response = $this->completeAdminLogin('root@example.test', self::PASSWORD, $secret)
            ->assertOk()
            ->assertJsonPath('data.expires_at', '2026-10-03T22:00:00+00:00')
            ->assertJsonPath('data.admin.id', $admin->id)
            ->assertJsonMissingPath('data.recovery_codes');

        $this->withToken($response->json('data.token'))->getJson('/api/v1/admin/me')->assertOk();
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.login', 'actor_type' => 'admin', 'actor_id' => $admin->id]);
    }

    public function test_a_wrong_code_is_refused_audited_and_leaves_the_challenge_usable(): void
    {
        $secret = $this->enrolTwoFactor($this->adminWithPassword(self::PASSWORD));
        $challenge = $this->login()->json('data.challenge_token');

        $this->twoFactor(['challenge_token' => $challenge, 'code' => '000000'])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.login_failed']);

        $this->twoFactor(['challenge_token' => $challenge, 'code' => $this->totp($secret)])->assertOk();
    }

    public function test_a_challenge_works_once(): void
    {
        $secret = $this->enrolTwoFactor($this->adminWithPassword(self::PASSWORD));
        $challenge = $this->login()->json('data.challenge_token');

        $this->twoFactor(['challenge_token' => $challenge, 'code' => $this->totp($secret, -1)])->assertOk();
        $this->twoFactor(['challenge_token' => $challenge, 'code' => $this->totp($secret, 0)])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:two-factor-challenge-expired');
    }

    public function test_a_challenge_expires_after_five_minutes(): void
    {
        $secret = $this->enrolTwoFactor($this->adminWithPassword(self::PASSWORD));
        $challenge = $this->login()->json('data.challenge_token');

        Carbon::setTestNow('2026-10-03 10:05:01');

        $this->twoFactor(['challenge_token' => $challenge, 'code' => $this->totp($secret)])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:two-factor-challenge-expired');
    }

    public function test_a_new_login_replaces_the_open_challenge(): void
    {
        $secret = $this->enrolTwoFactor($this->adminWithPassword(self::PASSWORD));
        $first = $this->login()->json('data.challenge_token');
        $second = $this->login()->json('data.challenge_token');

        $this->twoFactor(['challenge_token' => $first, 'code' => $this->totp($secret)])->assertUnauthorized();
        $this->twoFactor(['challenge_token' => $second, 'code' => $this->totp($secret)])->assertOk();
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $secret = $this->enrolTwoFactor($this->adminWithPassword(self::PASSWORD));
        $code = $this->totp($secret);

        $this->twoFactor(['challenge_token' => $this->login()->json('data.challenge_token'), 'code' => $code])->assertOk();
        $this->twoFactor(['challenge_token' => $this->login()->json('data.challenge_token'), 'code' => $code])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
    }

    public function test_wrong_codes_count_in_the_sign_in_throttle(): void
    {
        $secret = $this->enrolTwoFactor($this->adminWithPassword(self::PASSWORD));
        $challenge = $this->login()->json('data.challenge_token');

        for ($i = 0; $i < 5; $i++) {
            $this->twoFactor(['challenge_token' => $challenge, 'code' => '000000'])->assertUnauthorized();
        }

        $this->twoFactor(['challenge_token' => $challenge, 'code' => $this->totp($secret)])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_the_code_step_needs_a_code_or_a_recovery_code(): void
    {
        $this->twoFactor(['challenge_token' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['code', 'recovery_code']);
        $this->twoFactor(['challenge_token' => 'x', 'code' => '12345'])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_an_admin_without_a_second_factor_is_asked_to_enrol_and_the_first_code_confirms_it(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);

        $first = $this->login()
            ->assertOk()
            ->assertJsonPath('data.status', 'two_factor_enrolment_required')
            ->assertJsonStructure(['data' => ['challenge_token', 'enrolment' => ['secret', 'otpauth_url', 'qr_code']]]);
        $this->assertStringStartsWith('otpauth://totp/', $first->json('data.enrolment.otpauth_url'));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $first->json('data.enrolment.qr_code'));
        $this->assertNull($admin->refresh()->two_factor_confirmed_at);

        $response = $this->twoFactor([
            'challenge_token' => $first->json('data.challenge_token'),
            'code' => $this->totp($first->json('data.enrolment.secret')),
        ])->assertOk()->assertJsonCount(10, 'data.recovery_codes');

        $this->assertNotNull($admin->refresh()->two_factor_confirmed_at);
        $this->assertSame($first->json('data.enrolment.secret'), $admin->two_factor_secret);
        $this->assertSame(10, DB::table('identity_admin_recovery_codes')->where('admin_id', $admin->id)->count());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.two_factor_enabled', 'actor_id' => $admin->id]);
        $this->withToken($response->json('data.token'))->getJson('/api/v1/admin/me')->assertOk();
    }

    public function test_the_secret_is_stored_encrypted_and_recovery_codes_only_as_hashes(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);
        $response = $this->completeAdminLogin('root@example.test', self::PASSWORD)->assertOk();

        $stored = (string) DB::table('identity_admins')->where('id', $admin->id)->value('two_factor_secret');
        $this->assertNotSame('', $stored);
        $this->assertSame($admin->refresh()->two_factor_secret, Crypt::decryptString($stored));

        $hashes = DB::table('identity_admin_recovery_codes')->pluck('code_hash')->all();
        foreach ($response->json('data.recovery_codes') as $code) {
            $this->assertNotContains($code, $hashes);
        }
    }

    public function test_an_unconfirmed_enrolment_does_not_count_and_is_replaced_at_the_next_login(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);

        $first = $this->login()->json('data.enrolment.secret');
        $second = $this->login()->assertJsonPath('data.status', 'two_factor_enrolment_required')->json('data.enrolment.secret');

        $this->assertNotSame($first, $second);
        $this->assertSame($second, $admin->refresh()->two_factor_secret);
    }

    public function test_a_recovery_code_signs_in_once_and_is_audited(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);
        $codes = $this->completeAdminLogin('root@example.test', self::PASSWORD)->json('data.recovery_codes');

        $login = fn (string $code) => $this->twoFactor([
            'challenge_token' => $this->login()->json('data.challenge_token'),
            'recovery_code' => $code,
        ]);

        $login(strtoupper($codes[0]))->assertOk()->assertJsonMissingPath('data.recovery_codes');
        $login($codes[0])->assertUnauthorized()->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
        $login($codes[1])->assertOk();

        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'auth.recovery_code_used')->where('actor_id', $admin->id)->count());
    }

    public function test_a_recovery_code_does_not_work_while_enrolling(): void
    {
        $this->adminWithPassword(self::PASSWORD);

        $this->twoFactor(['challenge_token' => $this->login()->json('data.challenge_token'), 'recovery_code' => 'aaaaa-bbbbb'])->assertUnauthorized();
    }

    public function test_recovery_codes_are_regenerated_with_a_current_code(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);
        $secret = $this->enrolTwoFactor($admin);
        $token = $this->completeAdminLogin('root@example.test', self::PASSWORD, $secret, -1)->json('data.token');
        $old = DB::table('identity_admin_recovery_codes')->pluck('code_hash')->all();

        $this->app['auth']->forgetGuards();
        $new = $this->withToken($token)->postJson('/api/v1/admin/me/two-factor/recovery-codes', ['code' => $this->totp($secret, 0)])
            ->assertOk()->assertJsonCount(10, 'data.recovery_codes')->json('data.recovery_codes');

        $this->assertCount(10, $new);
        $this->assertSame([], array_intersect($old, DB::table('identity_admin_recovery_codes')->pluck('code_hash')->all()));
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.recovery_codes_regenerated', 'actor_id' => $admin->id]);
    }

    public function test_regenerating_recovery_codes_needs_a_right_unused_code(): void
    {
        $secret = $this->enrolTwoFactor($this->adminWithPassword(self::PASSWORD));
        $token = $this->completeAdminLogin('root@example.test', self::PASSWORD, $secret, -1)->json('data.token');

        $this->postJson('/api/v1/admin/me/two-factor/recovery-codes', ['code' => '123456'])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/admin/me/two-factor/recovery-codes', ['code' => '000000'])
            ->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/admin/me/two-factor/recovery-codes', ['code' => $this->totp($secret, -1)])
            ->assertStatus(422);
    }

    public function test_the_console_reset_removes_the_second_factor_and_signs_the_admin_out(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);
        $this->completeAdminLogin('root@example.test', self::PASSWORD)->assertOk();
        $admin->createToken('other', ['*'], now()->addDay());

        $this->artisan('identity:reset-admin-two-factor', ['email' => 'ROOT@example.test'])->expectsConfirmation('Remove the second factor and every session of ROOT@example.test?', 'yes')->assertExitCode(0);

        $admin->refresh();
        $this->assertNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);
        $this->assertNull($admin->two_factor_last_step);
        $this->assertSame(0, $admin->tokens()->count());
        $this->assertSame(0, DB::table('identity_admin_recovery_codes')->count());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.two_factor_reset', 'actor_type' => 'system', 'subject_id' => $admin->id]);
        $this->login()->assertJsonPath('data.status', 'two_factor_enrolment_required');
    }

    public function test_the_console_reset_refuses_an_unknown_admin_and_an_unconfirmed_prompt(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);
        $secret = $this->enrolTwoFactor($admin);

        $this->artisan('identity:reset-admin-two-factor', ['email' => 'nobody@example.test'])->expectsConfirmation('Remove the second factor and every session of nobody@example.test?', 'yes')->assertExitCode(1);
        $this->artisan('identity:reset-admin-two-factor', ['email' => 'root@example.test'])->expectsConfirmation('Remove the second factor and every session of root@example.test?', 'no')->assertExitCode(1);

        $this->assertSame($secret, $admin->refresh()->two_factor_secret);
    }

    public function test_expired_and_used_challenges_are_purged_nightly(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);
        $this->enrolTwoFactor($admin);
        $now = now();
        $row = fn (string $hash, $expires, $used = null) => ['admin_id' => $admin->id, 'token_hash' => $hash, 'expires_at' => $expires, 'used_at' => $used, 'created_at' => $now];
        DB::table('identity_admin_two_factor_challenges')->insert([
            $row('expired', $now->copy()->subMinute()),
            $row('used', $now->copy()->addMinute(), $now),
            $row('open', $now->copy()->addMinute()),
        ]);

        Artisan::call('identity:purge-two-factor-challenges');

        $this->assertSame(['open'], DB::table('identity_admin_two_factor_challenges')->pluck('token_hash')->all());

        $events = collect($this->app->make(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'identity:purge-two-factor-challenges'));
        $this->assertCount(1, $events);
        $this->assertSame('45 2 * * *', $events->first()->expression);
    }

    public function test_a_suspended_admin_is_refused_at_the_code_step(): void
    {
        $admin = $this->adminWithPassword(self::PASSWORD);
        $secret = $this->enrolTwoFactor($admin);
        $challenge = $this->login()->json('data.challenge_token');
        $admin->forceFill(['status' => 'suspended'])->save();

        $this->twoFactor(['challenge_token' => $challenge, 'code' => $this->totp($secret)])->assertForbidden();
    }

    public function test_a_wrong_password_never_reaches_the_second_step(): void
    {
        $this->adminWithPassword(self::PASSWORD);

        $this->login('wrong-password')->assertUnauthorized();
        $this->assertSame(0, DB::table('identity_admin_two_factor_challenges')->count());
        $this->assertSame(1, AdminModel::query()->count());
        $this->assertTrue(Hash::check(self::PASSWORD, AdminModel::query()->first()->password));
    }
}
