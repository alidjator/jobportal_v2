<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Infrastructure\Auth\ThrottledPersonalAccessToken;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

final class TokenLastUsedTest extends FeatureTestCase
{
    private function lastUsedAt(): ?string
    {
        return ThrottledPersonalAccessToken::query()->firstOrFail()->getRawOriginal('last_used_at');
    }

    /** The guard keeps its user for the rest of a test, so it is reset to let every request authenticate the token again. */
    private function request(string $token): void
    {
        $this->app->make('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me');
    }

    public function test_sanctum_uses_the_throttled_token_model(): void
    {
        $this->assertSame(ThrottledPersonalAccessToken::class, Sanctum::$personalAccessTokenModel);
    }

    public function test_the_stamp_is_written_at_most_every_five_minutes(): void
    {
        $user = UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'password' => Hash::make('correct-horse-battery'),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-01-01 00:00:00',
        ]);
        $token = $user->createToken('device', ['*'], now()->addDay())->plainTextToken;
        $this->assertNull($this->lastUsedAt());

        $this->request($token);
        $first = $this->lastUsedAt();
        $this->assertNotNull($first);

        $this->travel(4)->minutes();
        $this->request($token);
        $this->assertSame($first, $this->lastUsedAt());

        $this->travel(2)->minutes();
        $this->request($token);
        $this->assertNotSame($first, $this->lastUsedAt());
    }

    public function test_any_other_change_to_the_token_is_saved_at_once(): void
    {
        $user = UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'password' => Hash::make('correct-horse-battery'),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-01-01 00:00:00',
        ]);
        $user->createToken('device', ['*'], now()->addDay());
        $token = ThrottledPersonalAccessToken::query()->firstOrFail();
        $token->forceFill(['last_used_at' => now()])->save();

        $token->forceFill(['last_used_at' => now(), 'name' => 'renamed'])->save();

        $this->assertSame('renamed', ThrottledPersonalAccessToken::query()->firstOrFail()->name);
    }
}
