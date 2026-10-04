<?php

namespace Tests\Feature\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Tests\FeatureTestCase;

/**
 * Verifies the Spatie permission package against the project's custom `identity_` tables, the composite pivot keys
 * and the `test_` prefix, using a throwaway model on `identity_admins` (the real admin model arrives with Identity).
 */
final class SpatiePermissionCompatibilityTest extends FeatureTestCase
{
    private Model $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = new class extends Model
        {
            use HasRoles;

            protected $table = 'identity_admins';

            protected $guarded = [];

            protected string $guard_name = 'admin';
        };

        $this->admin->forceFill([
            'name' => 'Probe Admin',
            'email' => 'probe-admin@example.test',
            'password' => 'not-a-real-hash',
        ])->save();
    }

    public function test_a_permission_granted_through_a_role_is_resolved(): void
    {
        $permission = Permission::create(['name' => 'audit_logs.view', 'guard_name' => 'admin']);
        $role = Role::create(['name' => 'auditor', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);

        $this->admin->assignRole($role);

        $this->assertTrue($this->admin->hasRole('auditor'));
        $this->assertTrue($this->admin->hasPermissionTo('audit_logs.view'));
        $this->assertSame(1, DB::table('identity_admin_roles')->where('model_id', $this->admin->getKey())->count());
        $this->assertSame(1, DB::table('identity_role_permissions')->count());
    }

    public function test_a_direct_permission_is_resolved_and_can_be_revoked(): void
    {
        Permission::create(['name' => 'settings.update', 'guard_name' => 'admin']);

        $this->admin->givePermissionTo('settings.update');
        $this->assertTrue($this->admin->hasDirectPermission('settings.update'));
        $this->assertSame(1, DB::table('identity_admin_permissions')->count());

        $this->admin->revokePermissionTo('settings.update');
        $this->assertFalse($this->admin->fresh()->hasPermissionTo('settings.update'));
    }

    public function test_the_package_uses_the_test_prefixed_tables(): void
    {
        $this->assertSame('test_', DB::connection()->getTablePrefix());
        $this->assertSame('identity_roles', (new Role)->getTable());
        $this->assertSame('identity_permissions', (new Permission)->getTable());
    }
}
