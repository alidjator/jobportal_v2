<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Permissions\PermissionDefinition;
use Tests\Concerns\SignsInAdmins;
use Tests\FeatureTestCase;

final class AdminRbacTest extends FeatureTestCase
{
    use SignsInAdmins;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->app->make(PermissionCatalog::class)->add(new PermissionDefinition('probe.read', 'probe'), new PermissionDefinition('probe.write', 'probe'));
    }

    /** @return list<string> */
    private function catalogNames(): array
    {
        return array_map(fn (PermissionDefinition $definition) => $definition->name, $this->app->make(PermissionCatalog::class)->all());
    }

    private function createAdmin(string $email = 'root@example.test', string $password = 'a-strong-password', string $repeat = 'a-strong-password'): PendingCommand
    {
        return $this->artisan('identity:create-admin', ['--name' => 'Root Admin', '--email' => $email])
            ->expectsQuestion('Password (minimum 8 characters)', $password)
            ->expectsQuestion('Repeat the password', $repeat);
    }

    public function test_the_catalog_is_synchronised_into_permissions_and_the_super_admin_role(): void
    {
        $this->assertSame(0, Artisan::call('identity:sync-permissions'));

        $permission = Permission::query()->where('name', 'probe.read')->where('guard_name', 'admin')->first();
        $this->assertSame('probe', $permission->group_name);

        $role = Role::query()->where('name', 'super_admin')->where('guard_name', 'admin')->first();
        $this->assertEqualsCanonicalizing($this->catalogNames(), $role->permissions->pluck('name')->all());
    }

    public function test_running_it_again_creates_no_duplicates_and_picks_up_new_permissions(): void
    {
        Artisan::call('identity:sync-permissions');
        $before = Permission::query()->count();
        Artisan::call('identity:sync-permissions');
        $this->assertSame($before, Permission::query()->count());

        $this->app->make(PermissionCatalog::class)->add(new PermissionDefinition('probe.delete', 'probe'));
        Artisan::call('identity:sync-permissions');

        $this->assertSame($before + 1, Permission::query()->count());
        $this->assertTrue(Role::query()->where('name', 'super_admin')->first()->hasPermissionTo('probe.delete'));
    }

    public function test_a_permission_no_longer_in_the_catalog_is_kept(): void
    {
        Permission::create(['name' => 'old.permission', 'guard_name' => 'admin']);

        Artisan::call('identity:sync-permissions');

        $this->assertTrue(Permission::query()->where('name', 'old.permission')->exists());
    }

    public function test_the_catalog_refuses_a_permission_registered_in_two_groups(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->app->make(PermissionCatalog::class)->add(new PermissionDefinition('probe.read', 'another-group'));
    }

    public function test_the_first_admin_is_created_with_the_super_admin_role_and_can_sign_in(): void
    {
        Artisan::call('identity:sync-permissions');

        $this->createAdmin()->expectsOutputToContain('Administrator root@example.test created')->assertExitCode(0);

        $admin = AdminModel::query()->where('email', 'root@example.test')->first();
        $this->assertSame('active', $admin->status);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertNotSame('a-strong-password', $admin->password);

        $token = $this->completeAdminLogin('root@example.test', 'a-strong-password')->assertOk()->json('data.token');
        $this->withToken($token)->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['super_admin']);

        $permissions = $this->withToken($token)->getJson('/api/v1/admin/me')->json('data.permissions');
        $expected = $this->catalogNames();
        sort($expected);
        $this->assertSame($expected, $permissions);
    }

    public function test_creation_fails_for_a_duplicate_email_a_password_mismatch_and_a_short_password(): void
    {
        Artisan::call('identity:sync-permissions');
        $this->createAdmin()->assertExitCode(0);

        $this->createAdmin()->expectsOutputToContain('already exists')->assertExitCode(1);
        $this->createAdmin('other@example.test', 'a-strong-password', 'different-password')->expectsOutputToContain('do not match')->assertExitCode(1);
        $this->createAdmin('other@example.test', 'short', 'short')->assertExitCode(1);

        $this->assertSame(1, AdminModel::query()->count());
    }

    public function test_without_a_synchronised_role_nothing_is_created(): void
    {
        $this->createAdmin()->expectsOutputToContain('Role not found')->assertExitCode(1);

        $this->assertSame(0, DB::table('identity_admins')->count());
    }
}
