<?php

namespace Tests\Feature\Content;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-platform-admin.md` section 7: announcements shown as a banner. */
final class AnnouncementTest extends FeatureTestCase
{
    private string $token;

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo('announcements.manage');
        $this->adminId = $admin->id;
        $this->token = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/admin'.$uri, $body);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Scheduled maintenance',
            'body' => 'The site is read-only on Sunday from 01:00 to 02:00.',
            'link_url' => 'https://status.example.test',
            'audience' => 'all',
            'level' => 'info',
            'starts_at' => now()->subHour()->toIso8601String(),
            'ends_at' => now()->addDays(3)->toIso8601String(),
            ...$overrides,
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    private function insert(array $overrides = []): int
    {
        return (int) DB::table('content_announcements')->insertGetId([
            'title' => 'Banner',
            'body' => 'Text',
            'audience' => 'all',
            'level' => 'info',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'created_by_admin_id' => $this->adminId,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    /** @return list<string> the titles of the live banners for the caller */
    private function banners(?string $token = null): array
    {
        $this->app['auth']->forgetGuards();
        $request = $token === null ? $this->flushHeaders() : $this->withToken($token);

        return $request->getJson('/api/v1/announcements')->assertOk()->json('data.*.title');
    }

    private function userToken(string $type): string
    {
        $user = UserModel::query()->create(['type' => $type, 'name' => 'Siti', 'email' => "{$type}@example.test", 'status' => 'active', 'locale' => 'id', 'email_verified_at' => now()]);

        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    public function test_an_admin_creates_changes_and_removes_an_announcement(): void
    {
        $created = $this->admin('POST', '/announcements', $this->payload())->assertCreated();
        $id = $created->json('data.id');

        $this->assertSame('live', $created->json('data.status'));
        $this->assertSame($this->adminId, $created->json('data.created_by_admin_id'));

        $changed = $this->admin('PUT', "/announcements/{$id}", $this->payload(['title' => 'New title', 'level' => 'important', 'link_url' => null]))->assertOk();
        $this->assertSame('New title', $changed->json('data.title'));
        $this->assertNull($changed->json('data.link_url'));
        $this->assertSame($this->adminId, $changed->json('data.created_by_admin_id'));
        $this->assertSame('important', $this->admin('GET', "/announcements/{$id}")->assertOk()->json('data.level'));

        $this->admin('DELETE', "/announcements/{$id}")->assertNoContent();
        $this->admin('GET', "/announcements/{$id}")->assertNotFound();

        $this->assertSame(
            ['content.announcement_created', 'content.announcement_updated', 'content.announcement_deleted'],
            DB::table('platform_audit_logs')->where('subject_type', 'announcement')->orderBy('id')->pluck('action')->all(),
        );
        $this->assertStringNotContainsString('maintenance', (string) DB::table('platform_audit_logs')->where('subject_type', 'announcement')->value('metadata'));
    }

    public function test_the_admin_list_shows_where_each_announcement_stands(): void
    {
        $this->insert(['title' => 'Past', 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDays(1)]);
        $this->insert(['title' => 'Now']);
        $this->insert(['title' => 'Later', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

        $list = $this->admin('GET', '/announcements')->assertOk();

        $this->assertSame(['Later', 'Now', 'Past'], $list->json('data.*.title'));
        $this->assertSame(['scheduled', 'live', 'ended'], $list->json('data.*.status'));
    }

    public function test_the_window_is_checked(): void
    {
        $this->admin('POST', '/announcements', $this->payload(['ends_at' => now()->subDay()->toIso8601String()]))->assertUnprocessable();
        $this->admin('POST', '/announcements', $this->payload(['ends_at' => now()->addDays(92)->toIso8601String()]))
            ->assertUnprocessable()->assertJsonPath('type', 'urn:onesis:error:invalid-announcement');
        $start = now()->subHour()->startOfSecond();
        $this->admin('POST', '/announcements', $this->payload(['starts_at' => $start->toIso8601String(), 'ends_at' => $start->copy()->addDays(90)->toIso8601String()]))->assertCreated();
    }

    public function test_the_fields_are_validated(): void
    {
        $this->admin('POST', '/announcements', $this->payload(['title' => str_repeat('a', 121)]))->assertUnprocessable();
        $this->admin('POST', '/announcements', $this->payload(['body' => str_repeat('a', 501)]))->assertUnprocessable();
        $this->admin('POST', '/announcements', $this->payload(['link_url' => 'http://insecure.example.test']))->assertUnprocessable();
        $this->admin('POST', '/announcements', $this->payload(['audience' => 'everyone']))->assertUnprocessable();
        $this->admin('POST', '/announcements', $this->payload(['level' => 'urgent']))->assertUnprocessable();
    }

    public function test_managing_needs_the_permission(): void
    {
        $other = AdminModel::query()->create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'hash', 'status' => 'active']);
        $token = $other->createToken('console', ['*'], now()->addHour())->plainTextToken;

        $this->admin('GET', '/announcements', token: $token)->assertForbidden();
        $this->admin('POST', '/announcements', $this->payload(), $token)->assertForbidden();
    }

    public function test_each_reader_sees_the_live_banners_of_their_audience(): void
    {
        $this->insert(['title' => 'Everyone', 'audience' => 'all']);
        $this->insert(['title' => 'Seekers', 'audience' => 'job_seekers']);
        $this->insert(['title' => 'Companies', 'audience' => 'company_members']);
        $this->insert(['title' => 'Past', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
        $this->insert(['title' => 'Later', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

        $this->assertSame(['Everyone'], $this->banners());
        $this->assertSame(['Seekers', 'Everyone'], $this->banners($this->userToken('job_seeker')));
        $this->assertSame(['Companies', 'Everyone'], $this->banners($this->userToken('company_member')));
    }

    public function test_important_banners_come_first_and_a_banner_carries_what_the_page_needs(): void
    {
        $this->insert(['title' => 'Info', 'starts_at' => now()->subHour()]);
        $this->insert(['title' => 'Important', 'level' => 'important', 'link_url' => 'https://example.test/x', 'starts_at' => now()->subDay()]);

        $this->app['auth']->forgetGuards();
        $data = $this->flushHeaders()->getJson('/api/v1/announcements')->assertOk()->json('data');

        $this->assertSame(['Important', 'Info'], array_column($data, 'title'));
        $this->assertSame('important', $data[0]['level']);
        $this->assertSame('https://example.test/x', $data[0]['link_url']);
        $this->assertSame(['id', 'title', 'body', 'link_url', 'level', 'starts_at', 'ends_at'], array_keys($data[0]));
    }

    public function test_the_live_list_is_cached_for_a_minute_per_audience(): void
    {
        $id = $this->insert(['title' => 'Everyone']);
        $this->assertSame(['Everyone'], $this->banners());

        DB::table('content_announcements')->where('id', $id)->delete();
        $this->assertSame(['Everyone'], $this->banners());

        Carbon::setTestNow(now()->addSeconds(61));
        $this->assertSame([], $this->banners());
    }
}
