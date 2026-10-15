<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

final class SignInThrottleLayersTest extends FeatureTestCase
{
    private const string PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 10:00:00');
        UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'password' => Hash::make(self::PASSWORD), 'status' => 'active', 'locale' => 'en']);
        AdminModel::query()->create(['name' => 'Root', 'email' => 'root@example.test', 'password' => Hash::make(self::PASSWORD), 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function attempt(string $uri, string $email, string $password, string $address): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $address])
            ->postJson($uri, ['email' => $email, 'password' => $password, 'confirm' => true]);
    }

    /** Fails `$count` times for one email, four failures per address so the per-minute layer never trips. */
    private function failAcrossAddresses(string $uri, string $email, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->attempt($uri, $email, 'nope', '10.1.0.'.(intdiv($i, 4) + 1))->assertStatus(401);
        }
    }

    public function test_an_account_is_locked_for_the_hour_after_20_failures_from_any_addresses(): void
    {
        $this->failAcrossAddresses('/api/v1/auth/login', 'siti@example.test', 20);

        $this->attempt('/api/v1/auth/login', 'siti@example.test', self::PASSWORD, '10.2.0.1')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('type', 'urn:onesis:error:too-many-requests');

        Carbon::setTestNow('2026-10-03 11:00:01');

        $this->attempt('/api/v1/auth/login', 'siti@example.test', self::PASSWORD, '10.2.0.1')->assertOk();
    }

    public function test_an_address_is_locked_for_the_hour_after_30_failures_over_any_accounts(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->attempt('/api/v1/auth/login', "nobody{$i}@example.test", 'nope', '10.3.0.1')->assertStatus(401);
        }

        $this->attempt('/api/v1/auth/login', 'siti@example.test', self::PASSWORD, '10.3.0.1')->assertStatus(429)->assertHeader('Retry-After');
        $this->attempt('/api/v1/admin/auth/login', 'root@example.test', self::PASSWORD, '10.3.0.1')->assertStatus(429);
        $this->attempt('/api/v1/auth/login', 'siti@example.test', self::PASSWORD, '10.3.0.2')->assertOk();
    }

    public function test_a_successful_sign_in_does_not_reset_the_hourly_layers(): void
    {
        $this->failAcrossAddresses('/api/v1/auth/login', 'siti@example.test', 19);

        $this->attempt('/api/v1/auth/login', 'siti@example.test', self::PASSWORD, '10.4.0.1')->assertOk();
        $this->attempt('/api/v1/auth/login', 'siti@example.test', 'nope', '10.4.0.2')->assertStatus(401);

        $this->attempt('/api/v1/auth/login', 'siti@example.test', self::PASSWORD, '10.4.0.3')->assertStatus(429);
    }

    public function test_the_admin_sign_in_has_the_same_account_layer_and_a_separate_counter_from_the_user_sign_in(): void
    {
        $this->failAcrossAddresses('/api/v1/admin/auth/login', 'root@example.test', 20);

        $this->attempt('/api/v1/admin/auth/login', 'root@example.test', self::PASSWORD, '10.5.0.1')->assertStatus(429);
        $this->attempt('/api/v1/auth/login', 'siti@example.test', self::PASSWORD, '10.5.0.1')->assertOk();
    }

    public function test_the_account_restore_has_the_same_account_layer(): void
    {
        $this->failAcrossAddresses('/api/v1/auth/account/restore', 'siti@example.test', 20);

        $this->attempt('/api/v1/auth/account/restore', 'siti@example.test', self::PASSWORD, '10.6.0.1')->assertStatus(429);
    }
}
