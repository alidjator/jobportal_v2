<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Src\Identity\Domain\Events\PasswordChanged;
use Src\Identity\Domain\Events\SignedOutEverywhere;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

final class PasswordChangeTest extends FeatureTestCase
{
    private const string PASSWORD = 'correct-horse-battery';

    private const string NEW_PASSWORD = 'a-brand-new-passphrase';

    /** @param  array<string, mixed>  $attributes */
    private function user(array $attributes = []): UserModel
    {
        return UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'password' => Hash::make(self::PASSWORD),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-01-01 00:00:00', ...$attributes,
        ]);
    }

    private function admin(): AdminModel
    {
        return AdminModel::query()->create(['name' => 'Root', 'email' => 'root@example.test', 'password' => Hash::make(self::PASSWORD), 'status' => 'active']);
    }

    private function token(UserModel|AdminModel $account): string
    {
        return $account->createToken('device', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>|null  $body */
    private function change(string $uri, string $token, ?array $body = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->putJson($uri, $body ?? [
            'current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ]);
    }

    private function logoutAll(string $uri, string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson($uri);
    }

    public function test_a_user_changes_the_password_and_the_other_devices_are_signed_out(): void
    {
        Event::fake([PasswordChanged::class]);
        $user = $this->user();
        $current = $this->token($user);
        $this->token($user);

        $this->change('/api/v1/me/password', $current)->assertNoContent();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->refresh()->password));
        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/v1/me')->assertOk();
        Event::assertDispatched(PasswordChanged::class, fn (PasswordChanged $e): bool => $e->accountId === $user->id && $e->scope->value === 'user');
    }

    public function test_an_admin_changes_the_password_and_the_other_devices_are_signed_out(): void
    {
        $admin = $this->admin();
        $current = $this->token($admin);
        $this->token($admin);

        $this->change('/api/v1/admin/me/password', $current)->assertNoContent();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $admin->refresh()->password));
        $this->assertSame(1, $admin->tokens()->count());
    }

    public function test_the_change_is_written_to_the_audit_log(): void
    {
        $user = $this->user();

        $this->change('/api/v1/me/password', $this->token($user))->assertNoContent();

        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.password_changed', 'actor_type' => 'user', 'actor_id' => $user->id]);
    }

    public function test_a_wrong_current_password_is_refused_and_changes_nothing(): void
    {
        $user = $this->user();
        $token = $this->token($user);

        $this->change('/api/v1/me/password', $token, [
            'current_password' => 'wrong-password-here', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-password');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->refresh()->password));
    }

    public function test_wrong_current_passwords_count_in_the_sign_in_throttle(): void
    {
        $user = $this->user();
        $token = $this->token($user);
        $wrong = ['current_password' => 'wrong-password-here', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        for ($i = 0; $i < 5; $i++) {
            $this->change('/api/v1/me/password', $token, $wrong)->assertStatus(422);
        }

        $this->change('/api/v1/me/password', $token)->assertStatus(429)->assertHeader('Retry-After');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->refresh()->password));
    }

    public function test_the_new_password_follows_the_password_rule(): void
    {
        $token = $this->token($this->user());

        $this->change('/api/v1/me/password', $token, ['current_password' => self::PASSWORD, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->change('/api/v1/me/password', $token, ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => 'different-one'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->change('/api/v1/me/password', $token, ['current_password' => self::PASSWORD, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_an_account_without_a_password_gets_password_not_set(): void
    {
        $user = $this->user(['password' => null]);

        $this->change('/api/v1/me/password', $this->token($user))
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:password-not-set');
    }

    public function test_changing_the_password_needs_a_signed_in_account_of_the_right_kind(): void
    {
        $this->putJson('/api/v1/me/password', [])->assertUnauthorized();
        $this->putJson('/api/v1/admin/me/password', [])->assertUnauthorized();
        $this->change('/api/v1/admin/me/password', $this->token($this->user()))->assertUnauthorized();
    }

    public function test_a_user_signs_out_everywhere_including_the_current_device(): void
    {
        Event::fake([SignedOutEverywhere::class]);
        $user = $this->user();
        $other = $this->user(['email' => 'other@example.test']);
        $current = $this->token($user);
        $this->token($user);
        $this->token($other);

        $this->logoutAll('/api/v1/auth/logout-all', $current)->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/v1/me')->assertUnauthorized();
        Event::assertDispatched(SignedOutEverywhere::class, fn (SignedOutEverywhere $e): bool => $e->accountId === $user->id);
    }

    public function test_an_admin_signs_out_everywhere_and_the_entry_is_audited(): void
    {
        $admin = $this->admin();
        $current = $this->token($admin);
        $this->token($admin);
        $before = PersonalAccessToken::query()->count();

        $this->logoutAll('/api/v1/admin/auth/logout-all', $current)->assertNoContent();

        $this->assertSame(0, $admin->tokens()->count());
        $this->assertSame($before - 2, PersonalAccessToken::query()->count());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'auth.signed_out_everywhere', 'actor_type' => 'admin', 'actor_id' => $admin->id]);
    }
}
