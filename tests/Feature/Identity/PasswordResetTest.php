<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Src\Identity\Infrastructure\Notifications\ResetPasswordCodeNotification;

class PasswordResetTest extends IdentityTestCase
{
    public function test_code_is_returned_only_in_local_or_testing(): void
    {
        $user = $this->makeUser();

        $code = $this->postJson('/api/password/email', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('data.message', 'We have emailed you password reset code')
            ->json('data.code');
        $this->assertIsInt($code);
        $this->assertSame((string) $code, DB::table('verification_codes')->where('user_id', $user->id)->value('code'));

        $this->app['env'] = 'production';
        $this->postJson('/api/password/email', ['email' => $user->email])
            ->assertOk()
            ->assertJsonMissingPath('data.code')
            ->assertJsonPath('data.message', 'We have emailed you password reset code');
    }

    public function test_requesting_new_code_replaces_old_and_sets_expiry(): void
    {
        $user = $this->makeUser();
        $this->postJson('/api/password/email', ['email' => $user->email])->assertOk();
        $this->postJson('/api/password/email', ['email' => $user->email])->assertOk();

        $rows = DB::table('verification_codes')->where('user_id', $user->id)->get();
        $this->assertCount(1, $rows);
        $this->assertTrue(now()->isBefore($rows[0]->expire_at));
    }

    public function test_reset_code_email_sent_when_mail_configured(): void
    {
        Notification::fake();
        config([
            'mail.mailers.smtp.transport' => 'smtp', 'mail.mailers.smtp.host' => 'h', 'mail.mailers.smtp.port' => 25,
            'mail.mailers.smtp.username' => 'u', 'mail.mailers.smtp.password' => 'p', 'mail.mailers.smtp.encryption' => 'tls',
            'mail.from.address' => 'a@b.test', 'mail.from.name' => 'N',
        ]);
        $user = $this->makeUser();

        $code = $this->postJson('/api/password/email', ['email' => $user->email])->json('data.code');

        Notification::assertSentTo($user, ResetPasswordCodeNotification::class, fn ($n) => $n->code === (string) $code);
    }

    public function test_send_code_validation(): void
    {
        $this->postJson('/api/password/email', [])->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson('/api/password/email', ['email' => 'nobody-'.uniqid().'@example.test'])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_reset_changes_password_once_and_revokes_tokens(): void
    {
        $user = $this->makeUser();
        $user->createToken('old');
        $code = $this->postJson('/api/password/email', ['email' => $user->email])->json('data.code');

        $this->postJson('/api/password/reset', ['email' => $user->email, 'code' => $code, 'password' => 'brand-new-pass'])
            ->assertOk()
            ->assertExactJson(['data' => ['message' => 'Password reset successfully']]);

        $hash = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertTrue(Hash::check('brand-new-pass', $hash));
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'brand-new-pass'])->assertOk();

        // sekali pakai
        $this->postJson('/api/password/reset', ['email' => $user->email, 'code' => $code, 'password' => 'another-pass-2'])
            ->assertStatus(400)->assertExactJson(['error' => 'Invalid code']);
    }

    public function test_wrong_code_is_rejected_and_does_not_burn_valid_code(): void
    {
        $user = $this->makeUser();
        $code = $this->postJson('/api/password/email', ['email' => $user->email])->json('data.code');
        $wrong = $code === 100000 ? 100001 : 100000;

        $this->postJson('/api/password/reset', ['email' => $user->email, 'code' => $wrong, 'password' => 'brand-new-pass'])
            ->assertStatus(400)->assertExactJson(['error' => 'Invalid code']);
        $this->assertSame(1, DB::table('verification_codes')->where('user_id', $user->id)->count());
    }

    public function test_code_of_another_user_is_rejected(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $code = $this->postJson('/api/password/email', ['email' => $a->email])->json('data.code');

        $this->postJson('/api/password/reset', ['email' => $b->email, 'code' => $code, 'password' => 'brand-new-pass'])
            ->assertStatus(400)->assertExactJson(['error' => 'Invalid code']);
    }

    public function test_expired_and_legacy_codes_are_rejected(): void
    {
        $user = $this->makeUser();
        $code = $this->postJson('/api/password/email', ['email' => $user->email])->json('data.code');

        foreach ([now()->subMinute(), null] as $expireAt) {
            DB::table('verification_codes')->where('user_id', $user->id)->update(['expire_at' => $expireAt]);
            $this->postJson('/api/password/reset', ['email' => $user->email, 'code' => $code, 'password' => 'brand-new-pass'])
                ->assertStatus(400)->assertExactJson(['error' => 'Code expired']);
        }

        $this->assertFalse(Hash::check('brand-new-pass', DB::table('users')->where('id', $user->id)->value('password')));
    }

    public function test_reset_validation(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/password/reset', [])->assertStatus(422)->assertJsonValidationErrors(['code', 'email', 'password']);
        $this->postJson('/api/password/reset', ['email' => $user->email, 'code' => '1', 'password' => 'short'])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
