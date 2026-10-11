<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

/** Phase 6, part 2: a psychologist signs in at the provider endpoints only, with a TOTP second factor that is enrolled at the first sign-in. */
final class PsychologistSignInTest extends FeatureTestCase
{
    private const string PASSWORD = 'correct-horse-battery';

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 10:00:00');

        $providerId = (int) DB::table('assessment_providers')->insertGetId(['code' => 'LPC', 'name' => 'Lembaga Psikologi Contoh', 'legal_name' => 'PT Lembaga', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->userId = $this->account('psychologist', 'dewi@example.test');
        DB::table('assessment_psychologists')->insert([
            'user_id' => $this->userId, 'provider_id' => $providerId, 'str_number' => 'STR-123', 'str_valid_until' => '2030-01-31',
            'silp_number' => 'SIPP-456', 'silp_valid_until' => '2029-06-30', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function account(string $type, string $email, string $status = 'active'): int
    {
        return (int) DB::table('identity_users')->insertGetId([
            'type' => $type, 'name' => 'Dewi Lestari', 'email' => $email, 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now(),
            'status' => $status, 'locale' => 'id', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function login(string $email = 'dewi@example.test', string $password = self::PASSWORD): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/provider/auth/login', ['email' => $email, 'password' => $password]);
    }

    /** @param  array<string, mixed>  $body */
    private function twoFactor(array $body): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/provider/auth/two-factor', $body);
    }

    private function totp(string $secret, int $stepOffset = 0): string
    {
        $google2fa = new Google2FA;

        return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + $stepOffset);
    }

    /** Both steps of the first sign-in, which enrols the second factor. Returns the secret and the whole answer. */
    private function enrol(): array
    {
        $first = $this->login()->assertOk();
        $secret = $first->json('data.enrolment.secret');
        $second = $this->twoFactor(['challenge_token' => $first->json('data.challenge_token'), 'code' => $this->totp($secret)])->assertOk();

        return [$secret, $second];
    }

    public function test_the_first_sign_in_asks_for_the_enrolment_and_never_answers_a_token(): void
    {
        $this->login()
            ->assertOk()
            ->assertJsonPath('data.status', 'two_factor_enrolment_required')
            ->assertJsonPath('data.expires_at', '2026-10-11T10:05:00+00:00')
            ->assertJsonStructure(['data' => ['challenge_token', 'enrolment' => ['secret', 'otpauth_url', 'qr_code']]])
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('identity_user_two_factor', ['user_id' => $this->userId, 'confirmed_at' => null]);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->userId)->count());
    }

    public function test_the_first_right_code_confirms_the_factor_and_signs_the_psychologist_in(): void
    {
        [, $response] = $this->enrol();

        $response->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', '2026-10-11T22:00:00+00:00')
            ->assertJsonPath('data.user.type', 'psychologist')
            ->assertJsonCount(10, 'data.recovery_codes');

        $this->assertNotNull(DB::table('identity_user_two_factor')->where('user_id', $this->userId)->value('confirmed_at'));
        $this->assertSame(10, DB::table('identity_user_recovery_codes')->where('user_id', $this->userId)->count());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.login', 'actor_type' => 'user', 'actor_id' => $this->userId]);

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('data.token'))->getJson('/api/v1/provider/me')->assertOk();
    }

    public function test_the_stored_secret_is_encrypted(): void
    {
        [$secret] = $this->enrol();

        $this->assertNotSame($secret, DB::table('identity_user_two_factor')->where('user_id', $this->userId)->value('secret'));
    }

    public function test_a_later_sign_in_asks_for_a_code_only(): void
    {
        [$secret] = $this->enrol();

        $first = $this->login()->assertOk()->assertJsonPath('data.status', 'two_factor_required')->assertJsonMissingPath('data.enrolment');

        $this->twoFactor(['challenge_token' => $first->json('data.challenge_token'), 'code' => $this->totp($secret, 1)])
            ->assertOk()
            ->assertJsonMissingPath('data.recovery_codes');
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        [$secret] = $this->enrol();
        $first = $this->login()->json('data.challenge_token');

        $this->twoFactor(['challenge_token' => $first, 'code' => $this->totp($secret, 0)])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
    }

    public function test_a_wrong_code_is_refused_audited_and_leaves_the_challenge_usable(): void
    {
        [$secret] = $this->enrol();
        $challenge = $this->login()->json('data.challenge_token');

        $this->twoFactor(['challenge_token' => $challenge, 'code' => '000000'])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.login_failed', 'actor_id' => $this->userId]);

        $this->twoFactor(['challenge_token' => $challenge, 'code' => $this->totp($secret, 1)])->assertOk();
    }

    public function test_a_challenge_works_once_and_expires_after_five_minutes(): void
    {
        [$secret] = $this->enrol();
        $used = $this->login()->json('data.challenge_token');
        $this->twoFactor(['challenge_token' => $used, 'code' => $this->totp($secret, 1)])->assertOk();

        $this->twoFactor(['challenge_token' => $used, 'code' => $this->totp($secret, -1)])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:two-factor-challenge-expired');

        $stale = $this->login()->json('data.challenge_token');
        Carbon::setTestNow('2026-10-11 10:05:01');

        $this->twoFactor(['challenge_token' => $stale, 'code' => $this->totp($secret, 1)])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:two-factor-challenge-expired');
    }

    public function test_a_recovery_code_signs_in_once(): void
    {
        [, $enrolled] = $this->enrol();
        $code = $enrolled->json('data.recovery_codes.0');

        $this->twoFactor(['challenge_token' => $this->login()->json('data.challenge_token'), 'recovery_code' => $code])->assertOk();
        $this->twoFactor(['challenge_token' => $this->login()->json('data.challenge_token'), 'recovery_code' => $code])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-two-factor-code');
    }

    public function test_a_recovery_code_cannot_finish_the_enrolment(): void
    {
        $first = $this->login()->assertOk();

        $this->twoFactor(['challenge_token' => $first->json('data.challenge_token'), 'recovery_code' => 'whatever'])->assertUnauthorized();
        $this->assertNull(DB::table('identity_user_two_factor')->where('user_id', $this->userId)->value('confirmed_at'));
    }

    public function test_a_wrong_password_and_an_unknown_email_get_the_same_answer(): void
    {
        $wrong = $this->login(password: 'not-the-password')->assertUnauthorized();
        $unknown = $this->login('nobody@example.test')->assertUnauthorized();

        $this->assertSame($wrong->json('type'), $unknown->json('type'));
        $this->assertSame($wrong->json('detail'), $unknown->json('detail'));
        $wrong->assertJsonPath('type', 'urn:jobportal:error:invalid-credentials');
    }

    public function test_a_job_seeker_cannot_use_the_provider_sign_in(): void
    {
        $this->account('job_seeker', 'seeker@example.test');

        $this->login('seeker@example.test')->assertUnauthorized()->assertJsonPath('type', 'urn:jobportal:error:invalid-credentials');
        $this->assertSame(0, DB::table('identity_user_two_factor')->count());
    }

    public function test_the_public_sign_in_refuses_a_psychologist_even_with_the_right_password(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/login', ['email' => 'dewi@example.test', 'password' => self::PASSWORD])
            ->assertUnauthorized()
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-credentials');
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->userId)->count());
    }

    public function test_the_account_restore_refuses_a_psychologist(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/account/restore', ['email' => 'dewi@example.test', 'password' => self::PASSWORD, 'confirm' => true])->assertUnauthorized();
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->userId)->count());
    }

    public function test_a_deactivated_psychologist_cannot_sign_in(): void
    {
        DB::table('identity_users')->where('id', $this->userId)->update(['status' => 'deactivated']);

        $this->login()->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:account-deactivated');
    }

    public function test_an_account_that_was_deactivated_after_the_password_step_is_refused_at_the_code_step(): void
    {
        $first = $this->login()->assertOk();
        DB::table('identity_users')->where('id', $this->userId)->update(['status' => 'deactivated']);

        $this->twoFactor(['challenge_token' => $first->json('data.challenge_token'), 'code' => $this->totp($first->json('data.enrolment.secret'))])->assertForbidden();
    }

    public function test_the_profile_shows_the_provider_and_whether_signing_is_allowed(): void
    {
        [, $enrolled] = $this->enrol();
        $this->app['auth']->forgetGuards();

        $this->withToken($enrolled->json('data.token'))->getJson('/api/v1/provider/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Dewi Lestari')
            ->assertJsonPath('data.email', 'dewi@example.test')
            ->assertJsonPath('data.provider.name', 'Lembaga Psikologi Contoh')
            ->assertJsonPath('data.str_number', 'STR-123')
            ->assertJsonPath('data.silp_valid_until', '2029-06-30')
            ->assertJsonPath('data.can_sign_reports', true);

        DB::table('assessment_psychologists')->where('user_id', $this->userId)->update(['silp_valid_until' => '2026-10-10']);
        $this->app['auth']->forgetGuards();

        $this->withToken($enrolled->json('data.token'))->getJson('/api/v1/provider/me')->assertOk()->assertJsonPath('data.can_sign_reports', false);
    }

    public function test_a_suspended_provider_stops_its_psychologists_from_signing(): void
    {
        [, $enrolled] = $this->enrol();
        DB::table('assessment_providers')->update(['status' => 'suspended']);
        $this->app['auth']->forgetGuards();

        $this->withToken($enrolled->json('data.token'))->getJson('/api/v1/provider/me')->assertOk()->assertJsonPath('data.can_sign_reports', false);
    }

    public function test_the_profile_needs_a_psychologist(): void
    {
        $seeker = $this->account('job_seeker', 'seeker@example.test');
        $token = UserModel::query()->findOrFail($seeker)->createToken('api', ['*'], now()->addHour())->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/provider/me')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/provider/me')->assertUnauthorized();
    }

    public function test_a_psychologist_token_opens_nothing_of_the_job_seeker_area(): void
    {
        [, $enrolled] = $this->enrol();
        $this->app['auth']->forgetGuards();

        $this->withToken($enrolled->json('data.token'))->getJson('/api/v1/me/profile')->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
    }

    public function test_a_psychologist_cannot_delete_the_account_themselves(): void
    {
        [, $enrolled] = $this->enrol();
        $this->app['auth']->forgetGuards();

        $this->withToken($enrolled->json('data.token'))->postJson('/api/v1/me/deletion', ['password' => self::PASSWORD, 'confirm' => true])
            ->assertForbidden()
            ->assertJsonPath('type', 'urn:jobportal:error:psychologist-account-managed');
        $this->assertDatabaseHas('identity_users', ['id' => $this->userId, 'status' => 'active']);
    }

    public function test_the_psychologist_signs_out_at_the_provider_endpoint(): void
    {
        [, $enrolled] = $this->enrol();
        $token = $enrolled->json('data.token');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/v1/provider/auth/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/provider/me')->assertUnauthorized();
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->userId)->count());
    }

    public function test_the_nightly_purge_also_removes_the_old_challenges_of_psychologists(): void
    {
        $this->login()->assertOk();
        Carbon::setTestNow('2026-10-11 10:10:00');

        Artisan::call('identity:purge-two-factor-challenges');

        $this->assertSame(0, DB::table('identity_user_two_factor_challenges')->count());
    }
}
