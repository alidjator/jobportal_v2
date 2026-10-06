<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\Route;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Tests\FeatureTestCase;

final class VerifiedEmailGateTest extends FeatureTestCase
{
    private const string PREFIX = ContextServiceProvider::API_PREFIX.'/probe';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:user', 'verified.email'])->get(self::PREFIX.'/verified', fn () => ['ok' => true]);
        Route::middleware(['api', 'auth:user'])->get(self::PREFIX.'/open', fn () => ['ok' => true]);
    }

    private function token(?string $verifiedAt): string
    {
        $user = UserModel::query()->create([
            'type' => 'job_seeker',
            'name' => 'Siti',
            'email' => 'siti@example.test',
            'status' => 'active',
            'locale' => 'id',
            'email_verified_at' => $verifiedAt,
        ]);

        return $user->createToken('device', ['*'], now()->addDays(30))->plainTextToken;
    }

    public function test_an_unverified_account_is_refused_on_a_gated_route(): void
    {
        $this->withToken($this->token(null))->getJson(self::PREFIX.'/verified')
            ->assertStatus(409)
            ->assertJson(['type' => 'urn:jobportal:error:email-not-verified']);
    }

    public function test_a_verified_account_passes(): void
    {
        $this->withToken($this->token('2026-10-06 09:00:00'))->getJson(self::PREFIX.'/verified')->assertOk();
    }

    public function test_the_gate_is_opt_in_and_other_routes_stay_open_to_an_unverified_account(): void
    {
        $this->withToken($this->token(null))->getJson(self::PREFIX.'/open')->assertOk();
    }

    public function test_a_missing_token_is_a_401_not_a_409(): void
    {
        $this->getJson(self::PREFIX.'/verified')->assertStatus(401);
    }
}
