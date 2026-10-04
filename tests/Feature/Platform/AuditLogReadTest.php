<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\FeatureTestCase;

final class AuditLogReadTest extends FeatureTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->token = $this->adminToken(['audit_logs.view']);
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'auditor@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Auditor', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    private function logs(string $query = '', ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->getJson('/api/v1/admin/audit-logs'.$query);
    }

    /** @param  array<string, mixed>  $metadata */
    private function entry(string $time, string $action = 'auth.login', string $actorType = 'user', ?int $actorId = 1, ?string $subjectType = 'user', ?int $subjectId = 1, array $metadata = []): int
    {
        return (int) DB::table('platform_audit_logs')->insertGetId([
            'actor_type' => $actorType, 'actor_id' => $actorId, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'metadata' => $metadata === [] ? null : json_encode($metadata), 'ip_address' => '203.0.113.5', 'user_agent' => 'TestAgent/1.0', 'occurred_at' => $time,
        ]);
    }

    public function test_reading_needs_an_admin_with_the_audit_permission(): void
    {
        $this->getJson('/api/v1/admin/audit-logs')->assertStatus(401);
        $this->logs(token: $this->adminToken(['faqs.manage'], 'other@example.test'))->assertStatus(403);
    }

    public function test_entries_are_listed_newest_first_with_all_their_fields(): void
    {
        $this->entry('2026-10-01 08:00:00.000000');
        $this->entry('2026-10-03 09:30:15.250000', 'auth.login_failed', 'user', null, null, null, ['reason' => 'invalid-credentials']);

        $response = $this->logs()->assertOk();

        $this->assertSame(['auth.login_failed', 'auth.login'], array_column($response->json('data'), 'action'));
        $first = $response->json('data.0');
        $this->assertSame('2026-10-03T09:30:15+00:00', $first['occurred_at']);
        $this->assertSame('user', $first['actor_type']);
        $this->assertNull($first['actor_id']);
        $this->assertNull($first['subject_type']);
        $this->assertSame(['reason' => 'invalid-credentials'], $first['metadata']);
        $this->assertSame('203.0.113.5', $first['ip_address']);
        $this->assertSame('TestAgent/1.0', $first['user_agent']);
        $this->assertSame([], $response->json('data.1.metadata'));
    }

    public function test_the_list_is_cursor_paginated_without_a_total(): void
    {
        foreach (range(1, 5) as $minute) {
            $this->entry("2026-10-03 10:0{$minute}:00.000000", 'auth.login', 'user', $minute);
        }

        $first = $this->logs('?per_page=2')->assertOk();
        $this->assertSame([5, 4], array_column($first->json('data'), 'actor_id'));
        $this->assertSame(2, $first->json('meta.per_page'));
        $this->assertArrayNotHasKey('total', $first->json('meta'));
        $this->assertNotNull($first->json('meta.next_cursor'));
        $this->assertStringContainsString('cursor=', $first->json('links.next'));
        $this->assertStringContainsString('per_page=2', $first->json('links.next'));

        $second = $this->logs('?per_page=2&cursor='.$first->json('meta.next_cursor'))->assertOk();
        $this->assertSame([3, 2], array_column($second->json('data'), 'actor_id'));

        $third = $this->logs('?per_page=2&cursor='.$second->json('meta.next_cursor'))->assertOk();
        $this->assertSame([1], array_column($third->json('data'), 'actor_id'));
        $this->assertNull($third->json('meta.next_cursor'));
        $this->assertNull($third->json('links.next'));
    }

    public function test_entries_with_the_same_time_are_neither_repeated_nor_skipped(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->entry('2026-10-03 10:00:00.000000');
        }

        $seen = [];
        $cursor = null;

        do {
            $response = $this->logs('?per_page=2'.($cursor === null ? '' : "&cursor={$cursor}"))->assertOk();
            array_push($seen, ...array_column($response->json('data'), 'id'));
            $cursor = $response->json('meta.next_cursor');
        } while ($cursor !== null);

        $this->assertCount(5, $seen);
        $this->assertCount(5, array_unique($seen));
        $sorted = $seen;
        rsort($sorted);
        $this->assertSame($sorted, $seen);
    }

    public function test_entries_added_between_slices_do_not_shift_the_next_slice(): void
    {
        foreach (range(1, 4) as $minute) {
            $this->entry("2026-10-03 10:0{$minute}:00.000000", 'auth.login', 'user', $minute);
        }

        $first = $this->logs('?per_page=2')->assertOk();
        $this->entry('2026-10-03 11:00:00.000000', 'auth.login', 'user', 99);

        $second = $this->logs('?per_page=2&cursor='.$first->json('meta.next_cursor'))->assertOk();

        $this->assertSame([2, 1], array_column($second->json('data'), 'actor_id'));
    }

    public function test_each_filter_narrows_the_list(): void
    {
        $this->entry('2026-10-01 08:00:00.000000', 'auth.login', 'user', 1, 'user', 1);
        $this->entry('2026-10-02 08:00:00.000000', 'auth.login', 'admin', 7, 'admin', 7);
        $this->entry('2026-10-03 08:00:00.000000', 'auth.login_failed', 'user', 2, 'user', 2);
        $this->entry('2026-10-04 08:00:00.000000', 'account.anonymized', 'system', null, 'user', 2);

        $ids = fn (string $query): array => array_column($this->logs($query)->assertOk()->json('data'), 'action');

        $this->assertSame(['auth.login_failed'], $ids('?filter[action]=auth.login_failed'));
        $this->assertSame(['auth.login'], $ids('?filter[actor_type]=admin'));
        $this->assertSame(['auth.login_failed'], $ids('?filter[actor_type]=user&filter[actor_id]=2'));
        $this->assertSame(['account.anonymized', 'auth.login_failed'], $ids('?filter[subject_type]=user&filter[subject_id]=2'));
        $this->assertSame(['auth.login_failed', 'auth.login'], $ids('?filter[from]=2026-10-02T08:00:00Z&filter[to]=2026-10-03T08:00:00Z'));
        $this->assertSame(['account.anonymized'], $ids('?filter[from]=2026-10-03T08:00:01Z'));
        $this->assertSame([], $ids('?filter[action]=nothing.here'));
    }

    public function test_times_with_an_offset_are_converted_to_utc(): void
    {
        $this->entry('2026-10-03 01:00:00.000000');
        $this->entry('2026-10-03 03:00:00.000000', 'auth.login_failed');

        $actions = array_column($this->logs('?filter[from]=2026-10-03T09:00:00%2B07:00&filter[to]=2026-10-03T09:00:00%2B07:00')->assertOk()->json('data'), 'action');
        $this->assertSame([], $actions);

        // 10:00 at +07:00 is 03:00 UTC: it includes the entry at 03:00 and excludes the one at 01:00.
        $actions = array_column($this->logs('?filter[from]=2026-10-03T10:00:00%2B07:00')->assertOk()->json('data'), 'action');
        $this->assertSame(['auth.login_failed'], $actions);

        // 08:00 at +07:00 is 01:00 UTC: both entries.
        $actions = array_column($this->logs('?filter[from]=2026-10-03T08:00:00%2B07:00')->assertOk()->json('data'), 'action');
        $this->assertSame(['auth.login_failed', 'auth.login'], $actions);
    }

    public function test_invalid_filters_cursors_and_page_sizes_are_refused_with_422(): void
    {
        $this->logs('?filter[ip_address]=203.0.113.5')->assertStatus(422)->assertJsonValidationErrors(['filter'], 'errors');
        $this->logs('?filter[actor_type]=robot')->assertStatus(422);
        $this->logs('?filter[actor_id]=abc')->assertStatus(422);
        $this->logs('?filter[from]=not-a-date')->assertStatus(422);
        $this->logs('?filter[from]=2026-10-03&filter[to]=2026-10-01')->assertStatus(422);
        $this->logs('?per_page=101')->assertStatus(422)->assertJsonValidationErrors(['per_page'], 'errors');
        $this->logs('?per_page=0')->assertStatus(422);
        $this->logs('?per_page=100')->assertOk();
    }

    public function test_a_cursor_that_this_list_did_not_produce_is_refused(): void
    {
        $this->entry('2026-10-03 10:00:00.000000');

        foreach (['garbage', rtrim(strtr(base64_encode('{"a":1}'), '+/', '-_'), '='), rtrim(strtr(base64_encode('["not a time", 5]'), '+/', '-_'), '=')] as $cursor) {
            $this->logs('?cursor='.$cursor)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-cursor');
        }
    }

    public function test_the_log_is_read_only(): void
    {
        $id = $this->entry('2026-10-03 10:00:00.000000');

        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->app['auth']->forgetGuards();
            $this->withToken($this->token)->{$method.'Json'}('/api/v1/admin/audit-logs')->assertStatus(405);
            $this->app['auth']->forgetGuards();
            $this->withToken($this->token)->{$method.'Json'}("/api/v1/admin/audit-logs/{$id}")->assertStatus(404);
        }

        $this->assertSame(1, DB::table('platform_audit_logs')->count());
    }
}
