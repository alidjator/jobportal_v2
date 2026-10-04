<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Domain\Events\PasswordReset;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;
use Tests\Support\RecordingVerificationCodeSender;

final class PasswordResetTest extends FeatureTestCase
{
    private const string OLD_PASSWORD = 'the-old-password';

    private const string NEW_PASSWORD = 'a-brand-new-password';

    private RecordingVerificationCodeSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 10:00:00');
        $this->sender = new RecordingVerificationCodeSender;
        $this->app->instance(VerificationCodeSender::class, $this->sender);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attributes */
    private function user(array $attributes = []): UserModel
    {
        return UserModel::query()->create([
            'type' => 'job_seeker',
            'name' => 'Siti',
            'email' => 'siti@example.test',
            'password' => Hash::make(self::OLD_PASSWORD),
            'status' => 'active',
            'locale' => 'en',
            ...$attributes,
        ]);
    }

    private function forgot(string $email = 'siti@example.test'): TestResponse
    {
        return $this->postJson('/api/v1/auth/password/forgot', ['email' => $email]);
    }

    private function reset(string $code, string $email = 'siti@example.test', string $password = self::NEW_PASSWORD): TestResponse
    {
        return $this->postJson('/api/v1/auth/password/reset', ['email' => $email, 'code' => $code, 'password' => $password]);
    }

    private function wrongCode(string $correct): string
    {
        return $correct === '000000' ? '111111' : '000000';
    }

    public function test_an_active_user_receives_a_reset_code_in_their_language(): void
    {
        $this->user();

        $this->forgot('Siti@Example.test')->assertNoContent();

        $this->assertCount(1, $this->sender->sent);
        $sent = $this->sender->sent[0];
        $this->assertSame('password_reset', $sent['kind']);
        $this->assertSame('siti@example.test', $sent['email']);
        $this->assertSame('en', $sent['locale']);
        $this->assertSame('2026-10-03 10:15:00', $sent['expiresAt']->format('Y-m-d H:i:s'));

        $stored = DB::table('identity_verification_codes')->first();
        $this->assertSame('password_reset', $stored->purpose);
        $this->assertNotSame($sent['code'], $stored->code_hash);
    }

    public function test_the_response_is_the_same_for_unknown_suspended_and_deactivated_accounts_and_no_email_is_sent(): void
    {
        $this->user();
        $this->user(['email' => 'suspended@example.test', 'status' => 'suspended']);
        $this->user(['email' => 'deactivated@example.test', 'status' => 'deactivated']);

        $known = $this->forgot();
        $unknown = $this->forgot('ghost@example.test');
        $suspended = $this->forgot('suspended@example.test');
        $deactivated = $this->forgot('deactivated@example.test');

        foreach ([$known, $unknown, $suspended, $deactivated] as $response) {
            $response->assertNoContent();
            $this->assertSame('', $response->getContent());
        }

        $this->assertCount(1, $this->sender->sent);
        $this->assertSame(1, DB::table('identity_verification_codes')->count());
    }

    public function test_the_per_account_limits_are_applied_silently(): void
    {
        $this->user();

        $this->forgot()->assertNoContent();
        Carbon::setTestNow('2026-10-03 10:00:20');
        $this->forgot()->assertNoContent();

        $this->assertCount(1, $this->sender->sent);

        foreach ([1, 2, 3, 4] as $minute) {
            Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00')->addMinutes($minute));
            $this->forgot()->assertNoContent();
        }

        $this->assertCount(5, $this->sender->sent);

        Carbon::setTestNow('2026-10-03 10:05:00');
        $this->forgot()->assertNoContent();
        $this->assertCount(5, $this->sender->sent);
    }

    public function test_the_eleventh_request_from_one_address_in_an_hour_is_throttled_for_any_email(): void
    {
        foreach (range(1, 10) as $number) {
            $this->forgot("nobody{$number}@example.test")->assertNoContent();
        }

        $this->forgot('nobody11@example.test')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '3600')
            ->assertJsonPath('type', 'urn:jobportal:error:too-many-requests');

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody11@example.test'])->assertNoContent();
    }

    public function test_the_correct_code_sets_the_new_password_verifies_the_email_and_revokes_every_token(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->user();
        $user->createToken('phone', ['*'], now()->addDay());
        $user->createToken('laptop', ['*'], now()->addDay());
        $this->forgot();

        $this->reset($this->sender->lastCode())->assertNoContent();

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotNull(DB::table('identity_verification_codes')->value('consumed_at'));
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $e) => $e->userId === $user->id);

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::OLD_PASSWORD])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::NEW_PASSWORD])->assertOk();
    }

    public function test_the_code_works_only_once(): void
    {
        $this->user();
        $this->forgot();
        $code = $this->sender->lastCode();

        $this->reset($code)->assertNoContent();

        $this->reset($code, password: 'another-new-password')
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:verification-code-expired');
    }

    public function test_an_account_without_a_password_can_set_one(): void
    {
        $user = $this->user(['password' => null]);
        $this->forgot();

        $this->reset($this->sender->lastCode())->assertNoContent();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_a_wrong_code_counts_an_attempt_and_five_wrong_attempts_burn_the_code(): void
    {
        $user = $this->user();
        $this->forgot();
        $correct = $this->sender->lastCode();

        foreach (range(1, 5) as $attempt) {
            $this->reset($this->wrongCode($correct))
                ->assertStatus(422)
                ->assertJsonPath('type', 'urn:jobportal:error:invalid-verification-code');
            $this->assertSame($attempt, (int) DB::table('identity_verification_codes')->value('attempts'));
        }

        $this->reset($correct)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:verification-code-expired');
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_an_expired_code_is_refused(): void
    {
        $this->user();
        $this->forgot();

        Carbon::setTestNow('2026-10-03 10:15:01');

        $this->reset($this->sender->lastCode())->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:verification-code-expired');
    }

    public function test_unknown_suspended_and_deactivated_accounts_look_like_a_missing_code(): void
    {
        $this->user(['email' => 'suspended@example.test', 'status' => 'suspended']);

        foreach (['ghost@example.test', 'suspended@example.test'] as $email) {
            $this->reset('123456', $email)
                ->assertStatus(422)
                ->assertJsonPath('type', 'urn:jobportal:error:verification-code-expired');
        }
    }

    public function test_an_account_waiting_for_deletion_can_reset_its_password_but_stays_deactivated(): void
    {
        $user = $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-10-01 08:00:00']);
        $this->forgot()->assertNoContent();

        $this->assertCount(1, $this->sender->sent);
        $this->reset($this->sender->lastCode())->assertNoContent();

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertSame('deactivated', $user->status);
        $this->assertNotNull($user->deletion_requested_at);

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::NEW_PASSWORD])
            ->assertStatus(403)
            ->assertJsonPath('type', 'urn:jobportal:error:account-deactivated')
            ->assertJsonPath('detail', 'This account is scheduled for deletion on 2026-10-31. Cancel the deletion to restore it.');
    }

    public function test_an_anonymised_account_cannot_reset_its_password(): void
    {
        $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-08-01 08:00:00', 'anonymized_at' => '2026-09-01 08:00:00']);

        $this->forgot()->assertNoContent();

        $this->assertCount(0, $this->sender->sent);
    }

    public function test_the_request_is_validated(): void
    {
        $this->reset('12ab', 'not-an-email', 'short')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'code', 'password'], 'errors');

        $this->postJson('/api/v1/auth/password/forgot', [])->assertStatus(422)->assertJsonValidationErrors(['email'], 'errors');
    }

    public function test_reset_codes_and_verification_codes_cannot_be_swapped(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $token = $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
        $this->forgot();
        $resetCode = $this->sender->lastCode();

        $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $resetCode])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:verification-code-expired');

        Carbon::setTestNow('2026-10-03 10:02:00');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/email/resend')->assertNoContent();
        $verificationCode = $this->sender->lastCode();

        $this->reset($verificationCode)->assertStatus(422);
        $this->reset($resetCode)->assertNoContent();
    }
}
