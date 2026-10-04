<?php

namespace Tests\Feature\Notification;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

final class InAppNotificationsTest extends FeatureTestCase
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

    /** @param  array<string, mixed>  $data */
    private function notification(UserModel $owner, string $createdAt, ?string $readAt = null, string $type = 'application.moved_to_interview', array $data = ['application_id' => 7]): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id, 'type' => $type, 'notifiable_type' => 'user', 'notifiable_id' => $owner->id,
            'data' => json_encode($data), 'read_at' => $readAt, 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);

        return $id;
    }

    private function api(string $method, string $uri, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/me/notifications'.$uri);
    }

    public function test_every_route_needs_a_signed_in_user(): void
    {
        $id = (string) Str::uuid();

        $this->getJson('/api/v1/me/notifications')->assertStatus(401);
        $this->getJson('/api/v1/me/notifications/unread-count')->assertStatus(401);
        $this->postJson("/api/v1/me/notifications/{$id}/read")->assertStatus(401);
        $this->postJson('/api/v1/me/notifications/read-all')->assertStatus(401);
        $this->deleteJson("/api/v1/me/notifications/{$id}")->assertStatus(401);
    }

    public function test_the_list_holds_only_the_own_notifications_newest_first_with_type_and_data(): void
    {
        $this->notification($this->user, '2026-10-01 08:00:00', null, 'application.rejected', ['application_id' => 1]);
        $this->notification($this->user, '2026-10-03 09:30:15', '2026-10-03 10:00:00');
        $this->notification($this->makeUser('other@example.test'), '2026-10-02 00:00:00');

        $response = $this->api('GET', '')->assertOk();

        $this->assertSame(['application.moved_to_interview', 'application.rejected'], array_column($response->json('data'), 'type'));
        $first = $response->json('data.0');
        $this->assertSame(['application_id' => 7], $first['data']);
        $this->assertSame('2026-10-03T10:00:00+00:00', $first['read_at']);
        $this->assertSame('2026-10-03T09:30:15+00:00', $first['created_at']);
        $this->assertNull($response->json('data.1.read_at'));
        $this->assertNull($response->json('links.next'));
    }

    public function test_the_list_is_cursor_paginated_without_repeating_or_skipping(): void
    {
        foreach (range(1, 5) as $minute) {
            $this->notification($this->user, "2026-10-03 10:0{$minute}:00");
        }

        $first = $this->api('GET', '?per_page=2')->assertOk();
        $this->assertCount(2, $first->json('data'));
        $this->assertSame(['2026-10-03T10:05:00+00:00', '2026-10-03T10:04:00+00:00'], array_column($first->json('data'), 'created_at'));
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $second = $this->api('GET', '?per_page=2&cursor='.$cursor)->assertOk();
        $this->assertSame(['2026-10-03T10:03:00+00:00', '2026-10-03T10:02:00+00:00'], array_column($second->json('data'), 'created_at'));

        $third = $this->api('GET', '?per_page=2&cursor='.$second->json('meta.next_cursor'))->assertOk();
        $this->assertSame(['2026-10-03T10:01:00+00:00'], array_column($third->json('data'), 'created_at'));
        $this->assertNull($third->json('meta.next_cursor'));
    }

    public function test_notifications_created_in_the_same_second_are_neither_repeated_nor_skipped(): void
    {
        $ids = [];
        foreach (range(1, 4) as $ignored) {
            $ids[] = $this->notification($this->user, '2026-10-03 10:00:00');
        }

        $seen = [];
        $cursor = null;
        do {
            $page = $this->api('GET', '?per_page=3'.($cursor === null ? '' : '&cursor='.$cursor))->assertOk();
            $seen = [...$seen, ...array_column($page->json('data'), 'id')];
            $cursor = $page->json('meta.next_cursor');
        } while ($cursor !== null);

        sort($ids);
        sort($seen);
        $this->assertSame($ids, $seen);
    }

    public function test_the_list_can_be_filtered_by_read_state(): void
    {
        $unread = $this->notification($this->user, '2026-10-03 10:00:00');
        $read = $this->notification($this->user, '2026-10-03 11:00:00', '2026-10-03 12:00:00');

        $this->assertSame([$unread], array_column($this->api('GET', '?filter[read]=false')->assertOk()->json('data'), 'id'));
        $this->assertSame([$read], array_column($this->api('GET', '?filter[read]=true')->assertOk()->json('data'), 'id'));
        $this->assertCount(2, $this->api('GET', '')->json('data'));
    }

    public function test_invalid_filters_and_cursors_are_refused(): void
    {
        $this->api('GET', '?filter[read]=maybe')->assertStatus(422);
        $this->api('GET', '?filter[type]=x')->assertStatus(422);
        $this->api('GET', '?per_page=101')->assertStatus(422);
        $this->api('GET', '?cursor=not-a-cursor')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-cursor');
    }

    public function test_the_unread_count_ignores_read_and_foreign_notifications(): void
    {
        $this->notification($this->user, '2026-10-03 10:00:00');
        $this->notification($this->user, '2026-10-03 10:01:00');
        $this->notification($this->user, '2026-10-03 10:02:00', '2026-10-03 11:00:00');
        $this->notification($this->makeUser('other@example.test'), '2026-10-03 10:03:00');

        $this->api('GET', '/unread-count')->assertOk()->assertExactJson(['data' => ['count' => 2]]);
    }

    public function test_marking_one_as_read_sets_the_time_and_is_idempotent(): void
    {
        $id = $this->notification($this->user, '2026-10-03 10:00:00');

        $this->api('POST', "/{$id}/read")->assertNoContent();
        $this->assertSame('2026-10-04 10:00:00', DB::table('notifications')->where('id', $id)->value('read_at'));

        Carbon::setTestNow('2026-10-04 12:00:00');
        $this->api('POST', "/{$id}/read")->assertNoContent();
        $this->assertSame('2026-10-04 10:00:00', DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_marking_all_as_read_touches_only_the_own_unread_ones(): void
    {
        $mine = $this->notification($this->user, '2026-10-03 10:00:00');
        $alreadyRead = $this->notification($this->user, '2026-10-03 10:01:00', '2026-10-03 11:00:00');
        $other = $this->notification($this->makeUser('other@example.test'), '2026-10-03 10:02:00');

        $this->api('POST', '/read-all')->assertNoContent();

        $this->assertSame('2026-10-04 10:00:00', DB::table('notifications')->where('id', $mine)->value('read_at'));
        $this->assertSame('2026-10-03 11:00:00', DB::table('notifications')->where('id', $alreadyRead)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id', $other)->value('read_at'));
        $this->api('POST', '/read-all')->assertNoContent();
    }

    public function test_a_user_can_delete_only_their_own_notification(): void
    {
        $mine = $this->notification($this->user, '2026-10-03 10:00:00');
        $foreign = $this->notification($this->makeUser('other@example.test'), '2026-10-03 10:01:00');

        $this->api('DELETE', "/{$foreign}")->assertNotFound();
        $this->assertTrue(DB::table('notifications')->where('id', $foreign)->exists());

        $this->api('DELETE', "/{$mine}")->assertNoContent();
        $this->assertFalse(DB::table('notifications')->where('id', $mine)->exists());
        $this->api('DELETE', "/{$mine}")->assertNotFound();
    }

    public function test_another_users_or_unknown_notification_cannot_be_marked_as_read(): void
    {
        $foreign = $this->notification($this->makeUser('other@example.test'), '2026-10-03 10:01:00');

        $this->api('POST', "/{$foreign}/read")->assertNotFound();
        $this->assertNull(DB::table('notifications')->where('id', $foreign)->value('read_at'));
        $this->api('POST', '/'.Str::uuid().'/read')->assertNotFound();
        $this->api('POST', '/not-a-uuid/read')->assertNotFound();
    }

    public function test_the_purge_deletes_notifications_older_than_six_months_read_or_unread(): void
    {
        $this->notification($this->user, '2026-03-01 00:00:00');
        $this->notification($this->user, '2026-04-03 09:59:59', '2026-04-04 00:00:00');
        $kept = [
            $this->notification($this->user, '2026-04-04 10:00:00'),
            $this->notification($this->user, '2026-09-01 00:00:00', '2026-09-02 00:00:00'),
        ];

        $this->assertSame(0, Artisan::call('notification:purge-old'));
        $this->assertStringContainsString('Deleted 2 notifications.', Artisan::output());

        $this->assertEqualsCanonicalizing($kept, DB::table('notifications')->pluck('id')->all());
    }

    public function test_the_purge_works_through_more_than_one_batch(): void
    {
        $rows = [];
        foreach (range(1, 2500) as $index) {
            $rows[] = [
                'id' => (string) Str::uuid(), 'type' => 'application.rejected', 'notifiable_type' => 'user', 'notifiable_id' => $this->user->id,
                'data' => '{}', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }
        $this->notification($this->user, '2026-09-01 00:00:00');

        Artisan::call('notification:purge-old');

        $this->assertStringContainsString('Deleted 2500 notifications.', Artisan::output());
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_the_purge_is_scheduled_daily_at_half_past_three_without_overlap(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'notification:purge-old'));

        $this->assertCount(1, $events);
        $this->assertSame('30 3 * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
        $this->assertTrue($events->first()->onOneServer);
    }
}
