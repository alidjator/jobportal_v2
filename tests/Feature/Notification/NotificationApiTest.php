<?php

namespace Tests\Feature\Notification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Src\Notification\Domain\BroadcastAuthorizer;
use Src\Notification\Domain\PushFailed;
use Src\Notification\Domain\PushGateway;
use Tests\FeatureTestCase;

class FakePushGateway implements PushGateway
{
    public array $sent = [];

    public bool $fail = false;

    public function send(string $title, string $body, array $tokens): array
    {
        if ($this->fail) {
            throw new PushFailed('boom');
        }
        $this->sent[] = compact('title', 'body', 'tokens');

        return ['success_count' => count($tokens), 'failure_count' => 0];
    }
}

class NotificationApiTest extends FeatureTestCase
{
    private FakePushGateway $push;

    protected function setUp(): void
    {
        parent::setUp();
        $this->push = new FakePushGateway;
        $this->app->instance(PushGateway::class, $this->push);
    }

    private function user(): UserModel
    {
        $u = UserModel::create([
            'name' => 'N', 'username' => 'u'.Str::random(10), 'email' => Str::random(10).'@t.test',
            'password' => 'secret123', 'role' => 'candidate',
        ]);
        Sanctum::actingAs($u);

        return $u;
    }

    private function broadcaster(bool $allowed): void
    {
        $this->app->instance(BroadcastAuthorizer::class, new class($allowed) implements BroadcastAuthorizer
        {
            public function __construct(private bool $allowed) {}

            public function canBroadcast(): bool
            {
                return $this->allowed;
            }
        });
    }

    public function test_notifications_requires_auth(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized()->assertJsonStructure(['error']);
    }

    public function test_notifications_are_paginated_per_user(): void
    {
        $u = $this->user();
        $other = UserModel::create(['name' => 'O', 'username' => 'o'.Str::random(10), 'email' => Str::random(10).'@t.test', 'password' => 'secret123', 'role' => 'candidate']);
        foreach ([[$u->id, 12], [$other->id, 3]] as [$id, $n]) {
            for ($i = 0; $i < $n; $i++) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(), 'type' => 'X', 'notifiable_type' => 'App\Models\User',
                    'notifiable_id' => $id, 'data' => json_encode(['title' => 't'.$i]), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $res = $this->getJson('/api/notifications')->assertOk();
        $this->assertSame(12, $res->json('data.total'));
        $this->assertSame(10, $res->json('data.per_page'));
        $this->assertCount(10, $res->json('data.data'));
        $this->assertCount(2, $this->getJson('/api/notifications?page=2')->json('data.data'));
    }

    public function test_store_token_is_idempotent(): void
    {
        $u = $this->user();
        $token = Str::random(40);

        $first = $this->postJson('/api/store-token', ['token' => $token])->assertOk()
            ->assertJsonPath('data.message', 'Token successfully stored!')
            ->assertJsonPath('data.device_token.device_token', $token)
            ->assertJsonPath('data.device_token.user_id', $u->id);
        $this->postJson('/api/store-token', ['token' => $token])->assertOk()
            ->assertJsonPath('data.device_token.id', $first->json('data.device_token.id'));
        $this->assertSame(1, DB::table('device_tokens')->where('device_token', $token)->count());
    }

    public function test_store_token_validation_and_auth(): void
    {
        $this->postJson('/api/store-token', ['token' => 'x'])->assertUnauthorized();
        $this->user();
        $this->postJson('/api/store-token', [])->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_store_token_anonymous(): void
    {
        $token = Str::random(40);
        $this->postJson('/api/store-token-anonymous-user', [])->assertUnprocessable();
        $this->postJson('/api/store-token-anonymous-user', ['token' => $token])->assertOk()
            ->assertJsonPath('data.device_token.device_token', $token)
            ->assertJsonPath('data.device_token.user_id', null);
        $this->postJson('/api/store-token-anonymous-user', ['token' => $token])->assertOk();
        $this->assertSame(1, DB::table('device_tokens')->where('device_token', $token)->count());
    }

    public function test_send_notification_to_own_tokens_only(): void
    {
        $u = $this->user();
        $t1 = Str::random(30);
        $t2 = Str::random(30);
        $other = DB::table('users')->insertGetId(['name' => 'O', 'username' => 'o'.Str::random(10), 'email' => Str::random(10).'@t.test', 'password' => 'x', 'role' => 'candidate']);
        DB::table('device_tokens')->insert([['device_token' => $t1, 'user_id' => $u->id], ['device_token' => $t2, 'user_id' => $other]]);

        $this->postJson('/api/send-notification', ['title' => 'Hi', 'body' => 'There'])->assertOk()
            ->assertJsonPath('data.response.success_count', 1);
        $this->assertSame([$t1], $this->push->sent[0]['tokens']);
    }

    public function test_send_notification_without_tokens_validation_auth_and_failure(): void
    {
        $this->postJson('/api/send-notification', ['title' => 'a', 'body' => 'b'])->assertUnauthorized();
        $u = $this->user();
        $this->postJson('/api/send-notification', ['title' => 'a'])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson('/api/send-notification', ['title' => 'a', 'body' => 'b'])->assertOk()
            ->assertExactJson(['data' => ['message' => 'Not device token found']]);

        DB::table('device_tokens')->insert(['device_token' => Str::random(30), 'user_id' => $u->id]);
        $this->push->fail = true;
        $this->postJson('/api/send-notification', ['title' => 'a', 'body' => 'b'])->assertStatus(500)
            ->assertExactJson(['data' => ['message' => 'boom']]);
    }

    public function test_send_to_all_forbidden_for_non_admin(): void
    {
        $this->postJson('/api/send-notification-all-users', ['title' => 'a', 'body' => 'b'])->assertUnauthorized();
        $this->user();
        $this->broadcaster(false);
        $this->postJson('/api/send-notification-all-users', ['title' => 'a', 'body' => 'b'])
            ->assertForbidden()->assertExactJson(['error' => 'Forbidden']);
        $this->postJson('/api/send-notification-all-users', [])->assertForbidden();
        $this->assertSame([], $this->push->sent);
    }

    public function test_send_to_all_as_admin(): void
    {
        $this->user();
        $this->broadcaster(true);
        $t = Str::random(30);
        DB::table('device_tokens')->insert(['device_token' => $t]);

        $this->postJson('/api/send-notification-all-users', ['title' => 'a'])->assertUnprocessable();
        $this->postJson('/api/send-notification-all-users', ['title' => 'a', 'body' => 'b'])->assertOk()
            ->assertJsonStructure(['data' => ['response' => ['success_count']]]);
        $this->assertContains($t, $this->push->sent[0]['tokens']);
    }

    public function test_default_authorizer_denies_api_token_users(): void
    {
        $this->user();
        $this->postJson('/api/send-notification-all-users', ['title' => 'a', 'body' => 'b'])->assertForbidden();
    }
}
