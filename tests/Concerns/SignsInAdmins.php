<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;

/** Admin sign-in is two steps (password, then a code), so tests that sign an admin in through the API use these helpers. */
trait SignsInAdmins
{
    /** Gives the admin a confirmed second factor and returns its secret. */
    protected function enrolTwoFactor(AdminModel $admin): string
    {
        $secret = (new Google2FA)->generateSecretKey(32);
        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        return $secret;
    }

    /**
     * The code of the time step `$stepOffset` steps from now. The server accepts one step either way and refuses a step that is
     * not newer than the last accepted one, so a test that signs in several times uses -1, then 0, then 1.
     */
    protected function totp(string $secret, int $stepOffset = 0): string
    {
        $google2fa = new Google2FA;

        return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + $stepOffset);
    }

    /** Both steps of the admin sign-in. Without `$secret` the admin is enrolling and the secret comes from the first answer. */
    protected function completeAdminLogin(string $email, string $password, ?string $secret = null, int $stepOffset = 0): TestResponse
    {
        $first = $this->postJson('/api/v1/admin/auth/login', ['email' => $email, 'password' => $password])->assertOk();

        return $this->postJson('/api/v1/admin/auth/two-factor', [
            'challenge_token' => $first->json('data.challenge_token'),
            'code' => $this->totp($secret ?? $first->json('data.enrolment.secret'), $stepOffset),
        ]);
    }

    protected function adminWithPassword(string $password, string $email = 'root@example.test'): AdminModel
    {
        return AdminModel::query()->create(['name' => 'Root', 'email' => $email, 'password' => Hash::make($password), 'status' => 'active']);
    }
}
