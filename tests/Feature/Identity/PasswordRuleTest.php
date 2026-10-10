<?php

namespace Tests\Feature\Identity;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

final class PasswordRuleTest extends FeatureTestCase
{
    private const string BREACHED = 'a-breached-password';

    /** Replaces the default fake of `FeatureTestCase`: the first stub that matches wins, so a second `Http::fake()` would never be reached. */
    private function fakeBreachService(PromiseInterface $response): void
    {
        Http::swap(new Factory);
        Http::fake(['api.pwnedpasswords.com/*' => $response]);
    }

    /** Makes the breach service report the given password as seen `$count` times. */
    private function breached(string $password, int $count = 3): void
    {
        $hash = strtoupper(sha1($password));

        $this->fakeBreachService(Http::response(substr($hash, 5).':'.$count."\r\n0000000000000000000000000000000000A:1", 200));
    }

    /** @return array<string, mixed> */
    private function payload(string $password): array
    {
        return ['name' => 'Siti Aminah', 'email' => 'siti@example.test', 'password' => $password];
    }

    public function test_a_breached_password_is_refused_at_registration(): void
    {
        $this->breached(self::BREACHED);

        $this->postJson('/api/v1/auth/register', $this->payload(self::BREACHED))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'], 'errors');

        $this->assertSame(0, UserModel::query()->count());
    }

    public function test_a_password_that_is_not_breached_is_accepted(): void
    {
        $this->breached('some-other-password');

        $this->postJson('/api/v1/auth/register', $this->payload('a-fresh-password-9'))->assertCreated();
    }

    public function test_only_the_first_five_characters_of_the_hash_leave_the_server(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(self::BREACHED))->assertCreated();

        $hash = strtoupper(sha1(self::BREACHED));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5)
            && ! str_contains($request->url(), self::BREACHED));
    }

    public function test_the_password_is_accepted_when_the_breach_service_is_down(): void
    {
        $this->fakeBreachService(Http::response('down', 500));

        $this->postJson('/api/v1/auth/register', $this->payload(self::BREACHED))->assertCreated();
    }

    public function test_a_short_password_is_refused_without_asking_the_service(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload('short'))->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_a_breached_password_is_refused_at_reset(): void
    {
        $this->breached(self::BREACHED);

        $this->postJson('/api/v1/auth/password/reset', ['email' => 'siti@example.test', 'code' => '123456', 'password' => self::BREACHED])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'], 'errors');
    }

    public function test_a_breached_password_is_refused_by_the_admin_console_command(): void
    {
        $this->breached(self::BREACHED);

        $this->artisan('identity:create-admin', ['--name' => 'Root Admin', '--email' => 'root@example.test'])
            ->expectsQuestion('Password (minimum 8 characters)', self::BREACHED)
            ->expectsQuestion('Repeat the password', self::BREACHED)
            ->assertExitCode(1);
    }
}
