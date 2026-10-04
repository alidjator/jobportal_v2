<?php

namespace Tests\Feature\Notification;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

final class DeviceTokensTest extends FeatureTestCase
{
    private UserModel $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 10:00:00');
        $this->user = $this->makeUser('siti@example.test');
        $this->token = $this->user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUser(string $email): UserModel
    {
        return UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'en']);
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/me/device-tokens'.$uri, $body);
    }

    private function register(string $deviceToken, string $platform = 'android'): TestResponse
    {
        return $this->api('POST', '', ['token' => $deviceToken, 'platform' => $platform]);
    }

    public function test_both_routes_need_a_signed_in_user(): void
    {
        $this->postJson('/api/v1/me/device-tokens', ['token' => 'abc', 'platform' => 'ios'])->assertStatus(401);
        $this->deleteJson('/api/v1/me/device-tokens/abc')->assertStatus(401);
    }

    public function test_a_token_is_registered_for_the_user(): void
    {
        $this->register('fcm:token-1', 'ios')->assertNoContent();

        $row = DB::table('notification_device_tokens')->first();
        $this->assertSame($this->user->id, (int) $row->user_id);
        $this->assertSame('fcm:token-1', $row->token);
        $this->assertSame('ios', $row->platform);
        $this->assertSame('2026-10-04 10:00:00', $row->last_used_at);
    }

    public function test_registering_the_same_token_again_only_refreshes_it(): void
    {
        $this->register('fcm:token-1', 'android')->assertNoContent();
        Carbon::setTestNow('2026-10-05 08:00:00');
        $this->register('fcm:token-1', 'web')->assertNoContent();

        $this->assertSame(1, DB::table('notification_device_tokens')->count());
        $row = DB::table('notification_device_tokens')->first();
        $this->assertSame('web', $row->platform);
        $this->assertSame('2026-10-05 08:00:00', $row->last_used_at);
        $this->assertSame('2026-10-04 10:00:00', $row->created_at);
    }

    public function test_a_token_registered_by_another_user_moves_to_the_new_one(): void
    {
        $other = $this->makeUser('other@example.test');
        DB::table('notification_device_tokens')->insert([
            'user_id' => $other->id, 'token' => 'shared-phone', 'platform' => 'android', 'last_used_at' => '2026-10-01 00:00:00',
            'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00',
        ]);

        $this->register('shared-phone')->assertNoContent();

        $this->assertSame(1, DB::table('notification_device_tokens')->count());
        $this->assertSame($this->user->id, (int) DB::table('notification_device_tokens')->value('user_id'));
    }

    public function test_an_eleventh_token_replaces_the_least_recently_used_one(): void
    {
        foreach (range(1, 10) as $number) {
            Carbon::setTestNow(Carbon::parse('2026-10-04 10:00:00')->addMinutes($number));
            $this->register("token-{$number}")->assertNoContent();
        }

        // token-1 is the oldest; using it again makes token-2 the oldest.
        Carbon::setTestNow('2026-10-04 11:00:00');
        $this->register('token-1')->assertNoContent();
        Carbon::setTestNow('2026-10-04 11:05:00');
        $this->register('token-11')->assertNoContent();

        $tokens = DB::table('notification_device_tokens')->where('user_id', $this->user->id)->pluck('token')->all();
        $this->assertCount(10, $tokens);
        $this->assertNotContains('token-2', $tokens);
        $this->assertContains('token-1', $tokens);
        $this->assertContains('token-11', $tokens);
    }

    public function test_the_limit_applies_per_user(): void
    {
        $other = $this->makeUser('other@example.test');
        foreach (range(1, 3) as $number) {
            DB::table('notification_device_tokens')->insert([
                'user_id' => $other->id, 'token' => "other-{$number}", 'platform' => 'web', 'last_used_at' => '2026-01-01 00:00:00',
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
            ]);
        }

        foreach (range(1, 10) as $number) {
            $this->register("mine-{$number}")->assertNoContent();
        }

        $this->assertSame(3, DB::table('notification_device_tokens')->where('user_id', $other->id)->count());
        $this->assertSame(10, DB::table('notification_device_tokens')->where('user_id', $this->user->id)->count());
    }

    public function test_the_request_is_validated(): void
    {
        $this->api('POST', '', [])->assertStatus(422);
        $this->api('POST', '', ['token' => 'abc', 'platform' => 'windows'])->assertStatus(422);
        $this->api('POST', '', ['token' => str_repeat('a', 513), 'platform' => 'android'])->assertStatus(422);
        $this->assertSame(0, DB::table('notification_device_tokens')->count());
    }

    public function test_a_user_removes_only_their_own_token_and_removal_is_silent_otherwise(): void
    {
        $other = $this->makeUser('other@example.test');
        $this->register('mine')->assertNoContent();
        DB::table('notification_device_tokens')->insert([
            'user_id' => $other->id, 'token' => 'theirs', 'platform' => 'ios', 'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00',
        ]);

        $this->api('DELETE', '/theirs')->assertNoContent();
        $this->assertTrue(DB::table('notification_device_tokens')->where('token', 'theirs')->exists());

        $this->api('DELETE', '/mine')->assertNoContent();
        $this->assertFalse(DB::table('notification_device_tokens')->where('token', 'mine')->exists());

        $this->api('DELETE', '/mine')->assertNoContent();
        $this->api('DELETE', '/'.rawurlencode('never:registered'))->assertNoContent();
    }
}
