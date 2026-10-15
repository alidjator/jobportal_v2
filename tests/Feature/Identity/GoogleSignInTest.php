<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Src\Identity\Application\Auth\GoogleIdentityVerifier;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\UserRegistered;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\FakeGoogleIdentityVerifier;
use Tests\Support\RecordingVerificationCodeSender;

final class GoogleSignInTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string PASSWORD = 'correct-horse-battery';

    private FakeGoogleIdentityVerifier $google;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 10:00:00');
        $this->google = new FakeGoogleIdentityVerifier;
        $this->app->instance(GoogleIdentityVerifier::class, $this->google);
        $this->google->accept('token-siti', 'google-subject-1', 'Siti@Gmail.test');
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
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@gmail.test', 'password' => Hash::make(self::PASSWORD),
            'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-01-01 00:00:00', ...$attributes,
        ]);
    }

    private function signIn(string $token = 'token-siti', array $extra = [], array $headers = []): TestResponse
    {
        return $this->postJson('/api/v1/auth/google', ['id_token' => $token, ...$extra], $headers);
    }

    private function links(): int
    {
        return DB::table('identity_social_accounts')->where('provider', 'google')->count();
    }

    public function test_the_first_google_sign_in_creates_a_verified_job_seeker_without_a_password(): void
    {
        Event::fake([UserRegistered::class, SignedIn::class]);
        $this->campaignRowId('jobfair-2026');

        $response = $this->signIn(extra: ['campaign_code' => 'jobfair-2026'], headers: ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', '2026-11-02T10:00:00+00:00')
            ->assertJsonPath('data.user.type', 'job_seeker')
            ->assertJsonPath('data.user.name', 'Siti Aminah')
            ->assertJsonPath('data.user.email', 'siti@gmail.test')
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.locale', 'en');

        $user = UserModel::query()->findOrFail($response->json('data.user.id'));
        $this->assertNull($user->password);
        $this->assertSame('jobfair-2026', $user->registration_campaign_code);
        $this->assertSame(1, $this->links());
        $link = DB::table('identity_social_accounts')->first();
        $this->assertSame($user->id, (int) $link->user_id);
        $this->assertSame('google-subject-1', $link->provider_user_id);
        Event::assertDispatched(UserRegistered::class);
        Event::assertDispatched(SignedIn::class);

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk();
    }

    public function test_the_next_sign_in_finds_the_same_account_through_the_google_subject(): void
    {
        $first = $this->signIn()->assertOk()->json('data.user.id');

        $this->google->accept('token-renamed', 'google-subject-1', 'new-address@gmail.test');
        $second = $this->signIn('token-renamed')
            ->assertOk()
            ->assertJsonPath('data.is_new_user', false);

        $this->assertSame($first, $second->json('data.user.id'));
        $this->assertSame(1, UserModel::query()->count());
        $this->assertSame(1, $this->links());
        $this->assertSame('siti@gmail.test', UserModel::query()->first()->email);
    }

    public function test_a_missing_google_name_falls_back_to_the_part_before_the_at_sign(): void
    {
        $this->google->accept('token-nameless', 'google-subject-2', 'budi.santoso@gmail.test', name: null);

        $this->signIn('token-nameless')->assertOk()->assertJsonPath('data.user.name', 'budi.santoso');
    }

    public function test_an_existing_account_with_a_verified_email_is_linked_and_keeps_its_password(): void
    {
        $user = $this->user();
        $user->createToken('phone', ['*'], now()->addDay());

        $this->signIn()->assertOk()->assertJsonPath('data.is_new_user', false)->assertJsonPath('data.user.id', $user->id);

        $this->assertSame(1, $this->links());
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        $this->assertSame(2, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['email' => 'siti@gmail.test', 'password' => self::PASSWORD])->assertOk();
    }

    public function test_an_existing_account_with_an_unverified_email_loses_its_password_and_tokens_when_google_vouches_for_the_address(): void
    {
        $impostor = $this->user(['email_verified_at' => null]);
        $impostorToken = $impostor->createToken('impostor', ['*'], now()->addDay())->plainTextToken;

        $response = $this->signIn()->assertOk()->assertJsonPath('data.is_new_user', false)->assertJsonPath('data.user.email_verified', true);

        $impostor->refresh();
        $this->assertNull($impostor->password);
        $this->assertNotNull($impostor->email_verified_at);
        $this->assertSame(1, $impostor->tokens()->count());
        $this->assertSame(1, $this->links());

        $this->app['auth']->forgetGuards();
        $this->withToken($impostorToken)->getJson('/api/v1/me')->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'siti@gmail.test', 'password' => self::PASSWORD])->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk();
    }

    public function test_the_owner_can_set_a_password_afterwards_through_the_reset_flow(): void
    {
        $sender = new RecordingVerificationCodeSender;
        $this->app->instance(VerificationCodeSender::class, $sender);
        $this->signIn()->assertOk();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'siti@gmail.test'])->assertNoContent();
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'siti@gmail.test', 'code' => $sender->lastCode(), 'password' => 'my-new-password'])->assertNoContent();

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@gmail.test', 'password' => 'my-new-password'])->assertOk();
    }

    public function test_a_company_member_with_the_same_email_can_sign_in_with_google(): void
    {
        $member = $this->user(['type' => 'company_member']);

        $this->signIn()->assertOk()->assertJsonPath('data.user.id', $member->id)->assertJsonPath('data.user.type', 'company_member');
    }

    public function test_an_email_that_google_has_not_verified_is_refused(): void
    {
        $this->google->accept('token-unverified', 'google-subject-3', 'siti@gmail.test', emailVerified: false);
        $this->user(['email_verified_at' => null]);

        $this->signIn('token-unverified')
            ->assertStatus(401)
            ->assertJsonPath('type', 'urn:onesis:error:google-email-not-verified');

        $this->assertSame(0, $this->links());
        $this->assertNotNull(UserModel::query()->first()->password);
    }

    public function test_an_invalid_token_is_a_401_and_repeated_failures_are_throttled_per_address(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->signIn('forged-token')->assertStatus(401)->assertJsonPath('type', 'urn:onesis:error:invalid-google-token');
        }

        $this->signIn()->assertStatus(429)->assertHeader('Retry-After');
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->postJson('/api/v1/auth/google', ['id_token' => 'token-siti'])->assertOk();
    }

    public function test_a_suspended_account_is_refused_and_nothing_is_linked(): void
    {
        $this->user(['status' => 'suspended']);

        $this->signIn()->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:account-suspended');

        $this->assertSame(0, $this->links());
    }

    public function test_an_account_waiting_for_deletion_is_told_the_deletion_date(): void
    {
        $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-10-01 08:00:00']);

        $this->signIn()
            ->assertStatus(403)
            ->assertJsonPath('type', 'urn:onesis:error:account-deactivated')
            ->assertJsonPath('detail', 'This account is scheduled for deletion on 2026-10-31. Cancel the deletion to restore it.');
    }

    public function test_a_different_google_account_cannot_be_linked_to_an_account_that_already_has_one(): void
    {
        $user = $this->user();
        DB::table('identity_social_accounts')->insert(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'another-subject', 'email' => 'siti@gmail.test', 'created_at' => now(), 'updated_at' => now()]);

        $this->signIn()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:social-account-conflict');

        $this->assertSame(1, $this->links());
    }

    public function test_the_feature_being_off_or_google_being_down_is_a_503(): void
    {
        $this->google->unavailable = true;

        $this->signIn()->assertStatus(503)->assertJsonPath('type', 'urn:onesis:error:google-sign-in-unavailable');
    }

    public function test_the_request_is_validated(): void
    {
        $this->postJson('/api/v1/auth/google', [])->assertStatus(422)->assertJsonValidationErrors(['id_token'], 'errors');
        $this->signIn(extra: ['campaign_code' => 'bad code!'])->assertStatus(422)->assertJsonValidationErrors(['campaign_code'], 'errors');
    }

    public function test_the_sign_in_is_audited(): void
    {
        $user = $this->signIn()->assertOk()->json('data.user.id');

        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'auth.login')->where('actor_id', $user)->count());
    }
}
