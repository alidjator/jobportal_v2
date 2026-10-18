<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

/** `identity:send-imported-password-resets`: a reset code for the accounts the import left without a password. */
final class ImportedPasswordResetsTest extends FeatureTestCase
{
    /** @param  array<string, mixed>  $attributes */
    private function user(string $email, array $attributes = []): UserModel
    {
        return UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'id', 'email_verified_at' => now(), ...$attributes]);
    }

    private function suppress(string $email): void
    {
        DB::table('platform_email_suppressions')->insert(['email' => $email, 'reason' => 'hard_bounce', 'source' => 'test', 'created_at' => now()]);
    }

    /** @return list<string> */
    private function recipients(): array
    {
        return array_map(
            fn ($message): string => $message->getOriginalMessage()->getTo()[0]->getAddress(),
            $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all(),
        );
    }

    public function test_a_dry_run_counts_and_sends_nothing(): void
    {
        $this->user('a@example.test');
        $this->user('b@example.test');
        $this->user('gone@example.test');
        $this->suppress('gone@example.test');
        $this->user('has-password@example.test', ['password' => 'hash']);

        $this->artisan('identity:send-imported-password-resets')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame([], $this->recipients());
        $this->assertSame(0, DB::table('identity_verification_codes')->count());
    }

    public function test_it_sends_to_accounts_without_a_password_and_skips_the_suppressed_and_the_others(): void
    {
        $this->user('a@example.test');
        $this->user('gone@example.test');
        $this->suppress('gone@example.test');
        $this->user('has-password@example.test', ['password' => 'hash']);
        $this->user('suspended@example.test', ['status' => 'suspended']);
        $this->user('anonymised@example.test', ['anonymized_at' => now()]);
        $google = $this->user('google@example.test');
        DB::table('identity_social_accounts')->insert(['user_id' => $google->id, 'provider' => 'google', 'provider_user_id' => 'g-1', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('identity:send-imported-password-resets', ['--write' => true, '--delay' => 0])
            ->expectsConfirmation('Send up to 200 password reset emails?', 'yes')
            ->assertSuccessful();

        $this->assertSame(['a@example.test'], $this->recipients());
    }

    public function test_a_second_run_sends_nothing_twice_and_the_limit_caps_a_run(): void
    {
        foreach (['a', 'b', 'c'] as $name) {
            $this->user("{$name}@example.test");
        }

        $this->artisan('identity:send-imported-password-resets', ['--write' => true, '--limit' => 2, '--delay' => 0])
            ->expectsConfirmation('Send up to 2 password reset emails?', 'yes')
            ->assertSuccessful();
        $this->assertSame(['a@example.test', 'b@example.test'], $this->recipients());

        $this->artisan('identity:send-imported-password-resets', ['--write' => true, '--limit' => 2, '--delay' => 0])
            ->expectsConfirmation('Send up to 2 password reset emails?', 'yes')
            ->assertSuccessful();
        $this->assertSame(['a@example.test', 'b@example.test', 'c@example.test'], $this->recipients());
    }
}
