<?php

namespace Tests\Feature\Identity;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Domain\Events\AccountDeletionCancelled;
use Src\Identity\Domain\Events\AccountDeletionRequested;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\FileStorage;
use Src\Shared\Infrastructure\Storage\LaravelFileStorage;
use Tests\FeatureTestCase;
use Tests\Support\RecordingVerificationCodeSender;

final class AccountDeletionTest extends FeatureTestCase
{
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
            'name' => 'Siti Aminah',
            'email' => 'siti@example.test',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
            'locale' => 'en',
            'email_verified_at' => '2026-01-01 00:00:00',
            'last_login_at' => '2026-09-30 00:00:00',
            'registration_campaign_code' => 'jobfair-2026',
            ...$attributes,
        ]);
    }

    private function token(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    private function requestDeletion(string $token, array $body = ['password' => self::PASSWORD, 'confirm' => true]): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/me/deletion', $body);
    }

    public function test_requesting_deletion_deactivates_the_account_and_signs_it_out_everywhere(): void
    {
        Event::fake([AccountDeletionRequested::class]);
        $user = $this->user();
        $token = $this->token($user);
        $this->token($user);

        $this->requestDeletion($token)
            ->assertOk()
            ->assertExactJson(['data' => ['scheduled_for' => '2026-11-02T10:00:00+00:00']]);

        $user->refresh();
        $this->assertSame('deactivated', $user->status);
        $this->assertSame('2026-10-03 10:00:00', $user->deletion_requested_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, $user->tokens()->count());
        Event::assertDispatched(AccountDeletionRequested::class, fn (AccountDeletionRequested $e) => $e->userId === $user->id);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])
            ->assertStatus(403)
            ->assertJsonPath('detail', 'This account is scheduled for deletion on 2026-11-02. Cancel the deletion to restore it.');
    }

    public function test_the_password_and_the_confirmation_are_required(): void
    {
        $user = $this->user();
        $token = $this->token($user);

        $this->requestDeletion($token, ['password' => 'wrong-password', 'confirm' => true])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-password');

        $this->requestDeletion($token, ['password' => self::PASSWORD])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirm'], 'errors');

        $this->requestDeletion($token, ['password' => self::PASSWORD, 'confirm' => false])->assertStatus(422);

        $this->assertSame('active', $user->fresh()->status);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_an_account_without_a_password_must_set_one_first(): void
    {
        $user = $this->user(['password' => null]);

        $this->requestDeletion($this->token($user))
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:password-required');

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        $token = $this->token($this->user());

        foreach (range(1, 5) as $ignored) {
            $this->requestDeletion($token, ['password' => 'wrong-password', 'confirm' => true])->assertStatus(422);
        }

        $this->requestDeletion($token)
            ->assertStatus(429)
            ->assertJsonPath('type', 'urn:jobportal:error:too-many-requests');
    }

    public function test_deletion_requires_a_signed_in_user(): void
    {
        $this->postJson('/api/v1/me/deletion', ['password' => self::PASSWORD, 'confirm' => true])->assertStatus(401);
    }

    public function test_cancelling_restores_the_account_and_signs_the_user_in(): void
    {
        Event::fake([AccountDeletionCancelled::class]);
        $user = $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-10-01 08:00:00']);

        $response = $this->postJson('/api/v1/auth/account/restore', ['email' => 'Siti@Example.test', 'password' => self::PASSWORD, 'confirm' => true])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', '2026-11-02T10:00:00+00:00')
            ->assertJsonPath('data.user.status', 'active');

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNull($user->deletion_requested_at);
        Event::assertDispatched(AccountDeletionCancelled::class, fn (AccountDeletionCancelled $e) => $e->userId === $user->id);

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk();
    }

    public function test_cancelling_needs_valid_credentials_and_the_confirmation(): void
    {
        $user = $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-10-01 08:00:00']);

        $this->postJson('/api/v1/auth/account/restore', ['email' => 'siti@example.test', 'password' => 'nope', 'confirm' => true])
            ->assertStatus(401)
            ->assertJsonPath('type', 'urn:jobportal:error:invalid-credentials');
        $this->postJson('/api/v1/auth/account/restore', ['email' => 'ghost@example.test', 'password' => 'nope', 'confirm' => true])->assertStatus(401);
        $this->postJson('/api/v1/auth/account/restore', ['email' => 'siti@example.test', 'password' => self::PASSWORD])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirm'], 'errors');

        $this->assertSame('deactivated', $user->fresh()->status);
    }

    public function test_wrong_credentials_share_the_sign_in_attempt_limit(): void
    {
        $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-10-01 08:00:00']);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/account/restore', ['email' => 'siti@example.test', 'password' => 'nope', 'confirm' => true])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])->assertStatus(429);
    }

    public function test_cancelling_when_no_deletion_is_pending_is_a_409(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/account/restore', ['email' => 'siti@example.test', 'password' => self::PASSWORD, 'confirm' => true])
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:jobportal:error:deletion-not-requested');
    }

    public function test_a_user_who_forgot_the_password_can_still_restore_the_account(): void
    {
        $sender = new RecordingVerificationCodeSender;
        $this->app->instance(VerificationCodeSender::class, $sender);
        $user = $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-10-01 08:00:00']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'siti@example.test'])->assertNoContent();
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'siti@example.test', 'code' => $sender->lastCode(), 'password' => 'my-new-password'])->assertNoContent();

        $this->postJson('/api/v1/auth/account/restore', ['email' => 'siti@example.test', 'password' => 'my-new-password', 'confirm' => true])->assertOk();

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_the_anonymisation_erases_personal_data_and_keeps_the_statistical_columns(): void
    {
        Event::fake([UserAnonymized::class]);
        Storage::fake();
        Storage::put('avatars/siti.jpg', 'image-bytes');
        $user = $this->user(['type' => 'company_member', 'status' => 'deactivated', 'deletion_requested_at' => '2026-09-01 10:00:00', 'avatar_path' => 'avatars/siti.jpg']);
        $other = $this->user(['email' => 'other@example.test', 'status' => 'deactivated', 'deletion_requested_at' => '2026-09-01 10:00:00']);

        DB::table('identity_social_accounts')->insert(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'g-1', 'email' => 'siti@gmail.test', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('identity_terms_acceptances')->insert(['user_id' => $user->id, 'document_type' => 'terms', 'document_version' => 1, 'locale' => 'id', 'accepted_at' => now(), 'ip_address' => '203.0.113.9']);
        DB::table('identity_verification_codes')->insert(['subject_type' => 'user', 'subject_id' => $user->id, 'purpose' => 'password_reset', 'code_hash' => 'x', 'expires_at' => now()->addHour(), 'attempts' => 0, 'created_at' => now()]);
        $user->createToken('leftover', ['*'], now()->addDay());

        $this->assertSame(0, Artisan::call('identity:anonymize-due-accounts'));

        $user->refresh();
        $this->assertSame('Deleted user', $user->name);
        $this->assertSame("anonymized-{$user->id}@anonymized.invalid", $user->email);
        $this->assertNull($user->password);
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->last_login_at);
        $this->assertSame('2026-10-03 10:00:00', $user->anonymized_at->format('Y-m-d H:i:s'));
        $this->assertSame('company_member', $user->type);
        $this->assertSame('en', $user->locale);
        $this->assertSame('jobfair-2026', $user->registration_campaign_code);
        $this->assertSame('deactivated', $user->status);
        $this->assertSame('2026-09-01 10:00:00', $user->deletion_requested_at->format('Y-m-d H:i:s'));

        Storage::assertMissing('avatars/siti.jpg');
        $this->assertSame(0, DB::table('identity_social_accounts')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('identity_verification_codes')->where('subject_id', $user->id)->count());
        $this->assertSame(0, $user->tokens()->count());
        $acceptance = DB::table('identity_terms_acceptances')->where('user_id', $user->id)->first();
        $this->assertNotNull($acceptance);
        $this->assertNull($acceptance->ip_address);

        Event::assertDispatched(UserAnonymized::class, fn (UserAnonymized $e) => $e->userId === $user->id);
        $this->assertNotNull($other->fresh()->anonymized_at);
    }

    public function test_a_failing_file_deletion_rolls_the_anonymisation_back_and_the_next_run_retries(): void
    {
        $user = $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-09-01 10:00:00', 'avatar_path' => 'avatars/siti.jpg']);
        $this->app->instance(FileStorage::class, new class implements FileStorage
        {
            public function put(string $path, string $contents): void
            {
                throw new RuntimeException('storage unavailable');
            }

            public function get(string $path): ?string
            {
                throw new RuntimeException('storage unavailable');
            }

            public function delete(string $path): void
            {
                throw new RuntimeException('storage unavailable');
            }
        });

        try {
            Artisan::call('identity:anonymize-due-accounts');
            $this->fail('The failure must stop the run.');
        } catch (RuntimeException $e) {
            $this->assertSame('storage unavailable', $e->getMessage());
        }

        $user->refresh();
        $this->assertNull($user->anonymized_at);
        $this->assertSame('Siti Aminah', $user->name);
        $this->assertSame('avatars/siti.jpg', $user->avatar_path);

        Storage::fake();
        $this->app->instance(FileStorage::class, $this->app->make(LaravelFileStorage::class));
        Artisan::call('identity:anonymize-due-accounts');

        $this->assertNotNull($user->fresh()->anonymized_at);
    }

    public function test_only_accounts_past_the_grace_period_are_anonymised_and_a_second_run_does_nothing(): void
    {
        $due = $this->user(['email' => 'due@example.test', 'status' => 'deactivated', 'deletion_requested_at' => '2026-09-03 10:00:00']);
        $notYet = $this->user(['email' => 'notyet@example.test', 'status' => 'deactivated', 'deletion_requested_at' => '2026-09-03 10:00:01']);
        $active = $this->user(['email' => 'active@example.test']);
        $suspended = $this->user(['email' => 'suspended@example.test', 'status' => 'suspended']);

        Artisan::call('identity:anonymize-due-accounts');

        $this->assertNotNull($due->fresh()->anonymized_at);
        $this->assertNull($notYet->fresh()->anonymized_at);
        $this->assertNull($active->fresh()->anonymized_at);
        $this->assertNull($suspended->fresh()->anonymized_at);
        $this->assertSame('Anonymised 0 account(s).', trim($this->artisanOutput()));
    }

    public function test_the_anonymisation_also_purges_the_notification_data_of_the_user(): void
    {
        $user = $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-09-01 10:00:00']);
        $other = $this->user(['email' => 'other@example.test']);

        foreach ([$user, $other] as $account) {
            DB::table('notification_device_tokens')->insert(['user_id' => $account->id, 'token' => 'fcm-'.$account->id, 'platform' => 'android', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('notification_preferences')->insert(['user_id' => $account->id, 'key' => 'application_update', 'channel' => 'email', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'test', 'notifiable_type' => 'user', 'notifiable_id' => $account->id, 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        }

        Artisan::call('identity:anonymize-due-accounts');

        $this->assertSame(0, DB::table('notification_device_tokens')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('notification_preferences')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('notification_preferences')->where('user_id', $other->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $user->id)->count());
        $this->assertSame(1, DB::table('notification_device_tokens')->where('user_id', $other->id)->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $other->id)->count());
    }

    public function test_the_original_email_address_can_register_again_after_the_anonymisation(): void
    {
        $this->user(['status' => 'deactivated', 'deletion_requested_at' => '2026-09-01 10:00:00']);
        Artisan::call('identity:anonymize-due-accounts');

        $this->postJson('/api/v1/auth/register', ['name' => 'Siti Again', 'email' => 'siti@example.test', 'password' => 'a-long-enough-password'])->assertCreated();
    }

    public function test_the_anonymisation_is_scheduled_daily_without_overlap(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'identity:anonymize-due-accounts'));

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('0 2 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    public function test_tokens_use_the_short_account_kind_names(): void
    {
        $user = $this->user();
        $this->token($user);

        $this->assertSame('user', DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->value('tokenable_type'));
    }

    private function artisanOutput(): string
    {
        Artisan::call('identity:anonymize-due-accounts');

        return Artisan::output();
    }
}
