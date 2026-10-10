<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

final class ApiRateLimitTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['security.rate_limit.user' => 3, 'security.rate_limit.guest' => 2]);

        Route::middleware('api')->prefix('api/v1/probe')->get('limited', fn () => ['ok' => true]);
    }

    public function test_a_guest_is_limited_per_ip_address(): void
    {
        $this->getJson('/api/v1/probe/limited')->assertOk();
        $this->getJson('/api/v1/probe/limited')->assertOk();

        $this->getJson('/api/v1/probe/limited')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('type', 'urn:jobportal:error:too-many-requests');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/api/v1/probe/limited')->assertOk();
    }

    public function test_a_signed_in_user_has_the_account_limit_and_does_not_use_the_ip_budget(): void
    {
        $token = $this->user()->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        for ($call = 0; $call < 3; $call++) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson('/api/v1/probe/limited')->assertOk();
        }

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/probe/limited')->assertStatus(429);

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson('/api/v1/probe/limited')->assertOk();
    }

    public function test_a_token_that_is_not_valid_counts_as_a_guest(): void
    {
        $this->withToken('999|not-a-real-token')->getJson('/api/v1/probe/limited')->assertOk();
        $this->withToken('999|not-a-real-token')->getJson('/api/v1/probe/limited')->assertOk();
        $this->withToken('999|not-a-real-token')->getJson('/api/v1/probe/limited')->assertStatus(429);
    }

    public function test_the_limits_default_to_120_per_account_and_60_per_ip(): void
    {
        $this->assertSame(120, (include base_path('config/security.php'))['rate_limit']['user']);
        $this->assertSame(60, (include base_path('config/security.php'))['rate_limit']['guest']);
    }

    private function user(): UserModel
    {
        return UserModel::query()->create([
            'type' => 'job_seeker',
            'name' => 'Siti Aminah',
            'email' => 'siti@example.test',
            'password' => Hash::make('Correct-horse-9'),
            'status' => 'active',
            'locale' => 'en',
            'email_verified_at' => '2026-01-01 00:00:00',
        ]);
    }
}
