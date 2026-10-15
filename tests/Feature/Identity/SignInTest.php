<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignedIn;
use Src\Identity\Domain\Events\SignInFailed;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\SignsInAdmins;
use Tests\FeatureTestCase;

final class SignInTest extends FeatureTestCase
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

    /** @param  array<string, mixed>  $attributes */
    private function user(array $attributes = []): UserModel
    {
        return UserModel::query()->create([
            'type' => 'job_seeker',
            'name' => 'Siti',
            'email' => 'siti@example.test',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
            'locale' => 'id',
            ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function admin(array $attributes = []): AdminModel
    {
        return AdminModel::query()->create([
            'name' => 'Root',
            'email' => 'root@example.test',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
            ...$attributes,
        ]);
    }

    public function test_a_user_signs_in_and_the_token_works_for_30_days(): void
    {
        Event::fake([SignedIn::class]);
        $user = $this->user();

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'Siti@Example.test', 'password' => self::PASSWORD, 'device_name' => 'phone'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', '2026-11-02T10:00:00+00:00')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonMissingPath('data.user.password');

        $this->assertSame('2026-10-03 10:00:00', $user->fresh()->last_login_at->format('Y-m-d H:i:s'));
        $this->assertSame('phone', $user->tokens()->first()->name);
        Event::assertDispatched(SignedIn::class, fn (SignedIn $e) => $e->scope === AccountScope::User && $e->accountId === $user->id);

        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'siti@example.test');
    }

    public function test_a_wrong_password_and_an_unknown_email_give_the_same_401(): void
    {
        Event::fake([SignInFailed::class]);
        $this->user();

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => 'nope'])->assertStatus(401);
        $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.test', 'password' => 'nope'])->assertStatus(401);

        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
        $this->assertSame('urn:onesis:error:invalid-credentials', $wrongPassword->json('type'));
        Event::assertDispatchedTimes(SignInFailed::class, 2);
    }

    public function test_a_social_only_account_cannot_sign_in_with_a_password(): void
    {
        $this->user(['password' => null]);

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])
            ->assertStatus(401)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-credentials');
    }

    public function test_a_suspended_account_is_refused_only_after_the_password_is_verified(): void
    {
        $this->user(['status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => 'nope'])
            ->assertStatus(401);

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])
            ->assertStatus(403)
            ->assertJsonPath('type', 'urn:onesis:error:account-suspended');
    }

    public function test_a_deactivated_account_waiting_for_deletion_is_told_the_deletion_date(): void
    {
        $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-10-01 08:00:00']);

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])
            ->assertStatus(403)
            ->assertJsonPath('type', 'urn:onesis:error:account-deactivated')
            ->assertJsonPath('detail', 'This account is scheduled for deletion on 2026-10-31. Cancel the deletion to restore it.');
    }

    public function test_the_sixth_failed_attempt_in_a_minute_is_throttled_and_success_is_not_counted(): void
    {
        $this->user();
        $credentials = ['email' => 'siti@example.test', 'password' => 'nope'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $credentials)->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('type', 'urn:onesis:error:too-many-requests');

        $this->postJson('/api/v1/auth/login', ['email' => 'other@example.test', 'password' => 'nope'])->assertStatus(401);

        Carbon::setTestNow('2026-10-03 10:01:01');

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])->assertOk();
    }

    public function test_a_successful_sign_in_clears_the_failure_counter(): void
    {
        $this->user();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => 'nope'])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])->assertOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => 'nope'])->assertStatus(401);
        }
    }

    public function test_the_request_is_validated(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:validation-failed')
            ->assertJsonValidationErrors(['email', 'password'], 'errors');
    }

    public function test_logout_revokes_only_the_token_that_was_used(): void
    {
        $user = $this->user();
        $first = $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])->json('data.token');
        $second = $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])->json('data.token');

        $this->withToken($first)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->getJson('/api/v1/me')->assertStatus(401);
        $this->withToken($second)->getJson('/api/v1/me')->assertOk();
    }

    public function test_an_admin_signs_in_and_the_token_works_for_12_hours(): void
    {
        $admin = $this->admin();

        $response = $this->completeAdminLogin('root@example.test', self::PASSWORD)
            ->assertOk()
            ->assertJsonPath('data.expires_at', '2026-10-03T22:00:00+00:00')
            ->assertJsonPath('data.admin.id', $admin->id);

        $this->withToken($response->json('data.token'))->getJson('/api/v1/admin/me')->assertOk()->assertJsonPath('data.email', 'root@example.test');
        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_the_two_account_kinds_cannot_sign_in_on_each_others_endpoint(): void
    {
        $this->user();
        $this->admin();

        $this->postJson('/api/v1/admin/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'root@example.test', 'password' => self::PASSWORD])->assertStatus(401);
    }

    public function test_an_admin_logout_revokes_the_admin_token(): void
    {
        $admin = $this->admin();
        $token = $this->completeAdminLogin('root@example.test', self::PASSWORD)->json('data.token');

        $this->withToken($token)->postJson('/api/v1/admin/auth/logout')->assertNoContent();

        $this->assertSame(0, $admin->tokens()->count());
    }
}
