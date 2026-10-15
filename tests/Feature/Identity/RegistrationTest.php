<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\UserRegistered;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\RecordingVerificationCodeSender;

final class RegistrationTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string PASSWORD = 'a-long-enough-password';

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

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return ['name' => 'Siti Aminah', 'email' => 'Siti@Example.test', 'password' => self::PASSWORD, ...$overrides];
    }

    /** Registers a user and returns their token. */
    private function register(array $overrides = []): string
    {
        return $this->postJson('/api/v1/auth/register', $this->payload($overrides))->assertCreated()->json('data.token');
    }

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_a_job_seeker_registers_and_is_signed_in(): void
    {
        Event::fake([UserRegistered::class, SignedIn::class]);
        $this->campaignRowId('jobfair-2026');

        $response = $this->postJson('/api/v1/auth/register', $this->payload(['campaign_code' => 'jobfair-2026']), ['Accept-Language' => 'en'])
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', '2026-11-02T10:00:00+00:00')
            ->assertJsonPath('data.user.type', 'job_seeker')
            ->assertJsonPath('data.user.email', 'siti@example.test')
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonPath('data.user.locale', 'en')
            ->assertJsonPath('data.user.status', 'active');

        $user = UserModel::query()->findOrFail($response->json('data.user.id'));
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertSame('jobfair-2026', $user->registration_campaign_code);
        $this->assertSame('2026-10-03 10:00:00', $user->last_login_at->format('Y-m-d H:i:s'));
        Event::assertDispatched(UserRegistered::class, fn (UserRegistered $e) => $e->userId === $user->id && $e->userType === 'job_seeker');
        Event::assertDispatched(SignedIn::class);

        $this->forgetGuards();
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_the_default_language_is_indonesian_when_no_header_is_sent(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(), ['Accept-Language' => ''])
            ->assertCreated()
            ->assertJsonPath('data.user.locale', 'id');
    }

    public function test_a_code_is_emailed_to_the_new_account_and_only_its_hash_is_stored(): void
    {
        $this->register();

        $this->assertCount(1, $this->sender->sent);
        $sent = $this->sender->sent[0];
        $this->assertSame('siti@example.test', $sent['email']);
        $this->assertSame('Siti Aminah', $sent['name']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $sent['code']);
        $this->assertSame('2026-10-03 10:15:00', $sent['expiresAt']->format('Y-m-d H:i:s'));

        $stored = DB::table('identity_verification_codes')->first();
        $this->assertSame('email_verification', $stored->purpose);
        $this->assertSame('user', $stored->subject_type);
        $this->assertNotSame($sent['code'], $stored->code_hash);
        $this->assertStringNotContainsString($sent['code'], json_encode($stored));
        $this->assertSame(0, $stored->attempts);
    }

    public function test_an_email_that_is_already_registered_is_a_409_regardless_of_case(): void
    {
        $this->register();

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'SITI@example.TEST']))
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:email-already-registered');

        $this->assertSame(1, UserModel::query()->count());
    }

    public function test_the_fourth_attempt_for_one_email_in_an_hour_is_throttled_even_when_the_email_exists(): void
    {
        $this->register();

        $this->postJson('/api/v1/auth/register', $this->payload())->assertStatus(409);
        $this->postJson('/api/v1/auth/register', $this->payload())->assertStatus(409);

        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertStatus(429)
            ->assertHeader('Retry-After', '3600')
            ->assertJsonPath('type', 'urn:onesis:error:too-many-requests');

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'other@example.test']))->assertCreated();

        Carbon::setTestNow('2026-10-03 11:00:01');
        $this->postJson('/api/v1/auth/register', $this->payload())->assertStatus(409);
    }

    public function test_the_eleventh_attempt_from_one_address_in_an_hour_is_throttled(): void
    {
        foreach (range(1, 10) as $number) {
            $this->postJson('/api/v1/auth/register', $this->payload(['email' => "person{$number}@example.test"]))->assertCreated();
        }

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'person11@example.test']))->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/v1/auth/register', $this->payload(['email' => 'person11@example.test']))
            ->assertCreated();

        $this->assertSame(11, UserModel::query()->count());
    }

    public function test_invalid_requests_are_not_counted_against_the_limits(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/register', $this->payload(['password' => 'short']))->assertStatus(422);
        }

        $this->register();
    }

    public function test_the_request_is_validated(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['password' => 'short', 'campaign_code' => 'bad code!', 'name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password', 'campaign_code', 'name'], 'errors');

        $this->assertSame(0, UserModel::query()->count());
    }

    public function test_the_correct_code_verifies_the_email_once(): void
    {
        $token = $this->register();
        $code = $this->sender->lastCode();

        $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $code])->assertNoContent();

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertJsonPath('data.email_verified', true);
        $this->assertNotNull(DB::table('identity_verification_codes')->value('consumed_at'));

        $this->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $code])
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:email-already-verified');
    }

    public function test_a_wrong_code_counts_an_attempt_and_five_wrong_attempts_burn_the_code(): void
    {
        $token = $this->register();
        $correct = $this->sender->lastCode();
        $wrong = $correct === '000000' ? '111111' : '000000';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->forgetGuards();
            $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $wrong])
                ->assertStatus(422)
                ->assertJsonPath('type', 'urn:onesis:error:invalid-verification-code');
            $this->assertSame($attempt, (int) DB::table('identity_verification_codes')->value('attempts'));
        }

        $this->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $correct])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:verification-code-expired');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $token = $this->register();

        Carbon::setTestNow('2026-10-03 10:15:01');

        $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $this->sender->lastCode()])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:verification-code-expired');
    }

    public function test_the_code_must_be_six_digits(): void
    {
        $token = $this->register();

        $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => '12ab'])->assertStatus(422);
    }

    public function test_verification_requires_a_signed_in_user(): void
    {
        $this->postJson('/api/v1/auth/email/verify', ['code' => '123456'])->assertStatus(401);
        $this->postJson('/api/v1/auth/email/resend')->assertStatus(401);
    }

    public function test_a_new_code_cannot_be_requested_within_60_seconds(): void
    {
        $token = $this->register();

        Carbon::setTestNow('2026-10-03 10:00:20');

        $this->withToken($token)->postJson('/api/v1/auth/email/resend')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '40')
            ->assertJsonPath('type', 'urn:onesis:error:verification-code-cooldown');
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_a_resent_code_replaces_the_previous_one(): void
    {
        $token = $this->register();
        $first = $this->sender->lastCode();

        Carbon::setTestNow('2026-10-03 10:01:00');
        $this->withToken($token)->postJson('/api/v1/auth/email/resend')->assertNoContent();
        $second = $this->sender->lastCode();

        $this->assertCount(2, $this->sender->sent);

        if ($first !== $second) {
            $this->forgetGuards();
            $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $first])
                ->assertStatus(422)
                ->assertJsonPath('type', 'urn:onesis:error:invalid-verification-code');
        }

        $this->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $second])->assertNoContent();
    }

    public function test_at_most_five_codes_can_be_issued_per_hour(): void
    {
        $token = $this->register();

        foreach ([1, 2, 3, 4] as $minute) {
            Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00')->addMinutes($minute));
            $this->forgetGuards();
            $this->withToken($token)->postJson('/api/v1/auth/email/resend')->assertNoContent();
        }

        Carbon::setTestNow('2026-10-03 10:05:00');
        $this->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/email/resend')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '3300')
            ->assertJsonPath('type', 'urn:onesis:error:verification-code-limit-reached');

        Carbon::setTestNow('2026-10-03 11:00:01');
        $this->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/email/resend')->assertNoContent();
    }

    public function test_resending_for_a_verified_account_is_a_409(): void
    {
        $token = $this->register();
        $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $this->sender->lastCode()])->assertNoContent();

        Carbon::setTestNow('2026-10-03 10:05:00');
        $this->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/email/resend')
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:email-already-verified');
    }
}
