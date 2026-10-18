<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-platform-admin.md` section 6: the email suppression flag on `GET me` and on the admin account view (`users.view`). */
final class AdminUserViewTest extends FeatureTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo('users.view');
        $this->token = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    private function user(string $email, string $name = 'Siti Rahma', string $type = 'job_seeker'): UserModel
    {
        return UserModel::query()->create(['type' => $type, 'name' => $name, 'email' => $email, 'status' => 'active', 'locale' => 'id', 'email_verified_at' => now()]);
    }

    private function suppress(string $email, string $reason = 'hard_bounce'): void
    {
        DB::table('platform_email_suppressions')->insert(['email' => $email, 'reason' => $reason, 'source' => 'test', 'created_at' => now()]);
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/admin'.$uri, $body);
    }

    public function test_me_says_whether_the_address_still_receives_email(): void
    {
        $user = $this->user('siti@example.test');
        $token = $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email_deliverable', true);

        $this->suppress('siti@example.test');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email_deliverable', false);

        // A changed address starts clean: the list is keyed by the address.
        $user->forceFill(['email' => 'siti.new@example.test'])->save();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email_deliverable', true);
    }

    public function test_an_admin_searches_accounts_and_sees_the_suppression(): void
    {
        $this->user('siti@example.test', 'Siti Rahma');
        $this->user('budi@example.test', 'Budi Santoso', 'company_member');
        $this->suppress('budi@example.test', 'complaint');

        $all = $this->admin('GET', '/users')->assertOk();
        $this->assertSame(['budi@example.test', 'siti@example.test'], $all->json('data.*.email'));
        $this->assertSame([true, false], $all->json('data.*.email_suppressed'));
        $this->assertSame(['complaint', null], $all->json('data.*.email_suppression_reason'));

        $this->assertSame(['budi@example.test'], $this->admin('GET', '/users?q=SANTOSO')->assertOk()->json('data.*.email'));
        $this->assertSame(['siti@example.test'], $this->admin('GET', '/users?q=siti@')->assertOk()->json('data.*.email'));
    }

    public function test_opening_an_account_is_audited_and_does_not_show_an_anonymised_one(): void
    {
        $user = $this->user('siti@example.test');
        $gone = $this->user('gone@example.test');
        $gone->forceFill(['anonymized_at' => now()])->save();

        $shown = $this->admin('GET', "/users/{$user->id}")->assertOk();

        $this->assertSame('siti@example.test', $shown->json('data.email'));
        $this->assertFalse($shown->json('data.email_suppressed'));
        $this->assertNull($shown->json('data.email_suppression_reason'));
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'user.viewed')->where('subject_id', $user->id)->count());

        $this->admin('GET', "/users/{$gone->id}")->assertNotFound();
        $this->assertSame(['siti@example.test'], $this->admin('GET', '/users')->json('data.*.email'));
    }

    public function test_the_account_view_needs_the_users_view_permission(): void
    {
        $other = AdminModel::query()->create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'hash', 'status' => 'active']);
        $token = $other->createToken('console', ['*'], now()->addHour())->plainTextToken;

        $this->admin('GET', '/users', token: $token)->assertForbidden();
        $this->admin('GET', '/users/1', token: $token)->assertForbidden();
    }
}
