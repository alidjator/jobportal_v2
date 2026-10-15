<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Shared\Application\Permissions\PermissionCatalog;
use Src\Shared\Application\Permissions\PermissionDefinition;
use Tests\Concerns\SignsInAdmins;
use Tests\FeatureTestCase;

/** The operator's console commands for admin roles and administrators (docs/design/phase-8-admin-console.md). */
final class AdminConsoleTest extends FeatureTestCase
{
    use SignsInAdmins;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->app->make(PermissionCatalog::class)->add(new PermissionDefinition('probe.read', 'probe'), new PermissionDefinition('probe.write', 'probe'));
        Artisan::call('identity:sync-permissions');
    }

    private function admin(string $email, string $role = 'super_admin', string $status = 'active'): AdminModel
    {
        $admin = $this->adminWithPassword('a-strong-password', $email);
        $admin->forceFill(['status' => $status])->save();
        $admin->assignRole($role);

        return $admin;
    }

    private function createRole(string $name = 'moderator', array $permissions = ['probe.read']): void
    {
        $this->artisan('identity:create-role', ['name' => $name, '--permission' => $permissions])->assertExitCode(0);
    }

    public function test_a_role_is_created_with_catalog_permissions_and_audited(): void
    {
        $this->artisan('identity:create-role', ['name' => 'moderator', '--permission' => ['probe.write', 'probe.read', 'probe.read']])
            ->expectsOutput('Role moderator created with: probe.read, probe.write.')
            ->assertExitCode(0);

        $role = Role::query()->where('name', 'moderator')->where('guard_name', 'admin')->firstOrFail();
        $this->assertEqualsCanonicalizing(['probe.read', 'probe.write'], $role->permissions->pluck('name')->all());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'admin.role_created', 'actor_type' => 'system', 'subject_type' => 'admin_role']);
    }

    public function test_creating_a_role_refuses_a_bad_name_a_taken_name_and_unknown_or_missing_permissions(): void
    {
        $this->artisan('identity:create-role', ['name' => 'Bad Name', '--permission' => ['probe.read']])->assertExitCode(1);
        $this->artisan('identity:create-role', ['name' => 'super_admin', '--permission' => ['probe.read']])->assertExitCode(1);
        $this->artisan('identity:create-role', ['name' => 'moderator', '--permission' => ['probe.unknown']])
            ->expectsOutput('Unknown permission: probe.unknown.')
            ->assertExitCode(1);
        $this->artisan('identity:create-role', ['name' => 'moderator'])->assertExitCode(1);

        $this->assertFalse(Role::query()->where('name', 'moderator')->exists());
    }

    public function test_the_permissions_of_a_role_are_replaced_after_confirmation(): void
    {
        $this->createRole();

        $this->artisan('identity:set-role-permissions', ['name' => 'moderator', '--permission' => ['probe.write']])
            ->expectsOutput('Added: probe.write')
            ->expectsOutput('Removed: probe.read')
            ->expectsConfirmation('Change the permissions of moderator?', 'yes')
            ->assertExitCode(0);

        $this->assertSame(['probe.write'], Role::findByName('moderator', 'admin')->permissions->pluck('name')->all());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'admin.role_permissions_changed', 'subject_type' => 'admin_role']);
    }

    public function test_a_declined_change_and_the_super_admin_role_are_left_alone(): void
    {
        $this->createRole();

        $this->artisan('identity:set-role-permissions', ['name' => 'moderator', '--permission' => ['probe.write']])
            ->expectsConfirmation('Change the permissions of moderator?', 'no')
            ->assertExitCode(1);
        $this->artisan('identity:set-role-permissions', ['name' => 'super_admin', '--permission' => ['probe.read']])->assertExitCode(1);

        $this->assertSame(['probe.read'], Role::findByName('moderator', 'admin')->permissions->pluck('name')->all());
        $this->assertTrue(Role::findByName('super_admin', 'admin')->hasPermissionTo('probe.write'));
    }

    public function test_a_role_held_by_an_administrator_and_super_admin_cannot_be_deleted(): void
    {
        $this->createRole();
        $this->admin('mod@example.test', 'moderator');

        $this->artisan('identity:delete-role', ['name' => 'moderator'])->expectsConfirmation('Delete the role moderator?', 'yes')->assertExitCode(1);
        $this->artisan('identity:delete-role', ['name' => 'super_admin'])->expectsConfirmation('Delete the role super_admin?', 'yes')->assertExitCode(1);

        $this->assertTrue(Role::query()->where('name', 'moderator')->exists());
        $this->assertTrue(Role::query()->where('name', 'super_admin')->exists());
    }

    public function test_an_unused_role_is_deleted(): void
    {
        $this->createRole();

        $this->artisan('identity:delete-role', ['name' => 'moderator'])->expectsConfirmation('Delete the role moderator?', 'yes')->assertExitCode(0);

        $this->assertFalse(Role::query()->where('name', 'moderator')->exists());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'admin.role_deleted', 'subject_type' => 'admin_role']);
    }

    public function test_an_administrator_gets_another_role_that_limits_the_panel_at_once(): void
    {
        $this->admin('root@example.test');
        $other = $this->admin('other@example.test');
        $this->createRole();

        $this->artisan('identity:set-admin-role', ['email' => 'OTHER@example.test', 'role' => 'moderator'])
            ->expectsConfirmation('Give OTHER@example.test the role moderator?', 'yes')
            ->assertExitCode(0);

        $this->assertSame(['moderator'], $other->refresh()->getRoleNames()->all());
        $this->assertTrue($other->hasPermissionTo('probe.read'));
        $this->assertFalse($other->hasPermissionTo('probe.write'));
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'admin.role_changed', 'subject_type' => 'admin', 'subject_id' => $other->id]);
    }

    public function test_the_last_active_super_admin_keeps_the_role_and_stays_active(): void
    {
        $root = $this->admin('root@example.test');
        $this->admin('former@example.test', 'super_admin', 'suspended');
        $this->createRole();

        $this->artisan('identity:set-admin-role', ['email' => 'root@example.test', 'role' => 'moderator'])
            ->expectsConfirmation('Give root@example.test the role moderator?', 'yes')
            ->expectsOutput('This is the only active super_admin; give another administrator that role first.')
            ->assertExitCode(1);
        $this->artisan('identity:suspend-admin', ['email' => 'root@example.test'])
            ->expectsConfirmation('Suspend root@example.test and end every session?', 'yes')
            ->assertExitCode(1);

        $this->assertSame(['super_admin'], $root->refresh()->getRoleNames()->all());
        $this->assertSame('active', $root->status);
    }

    public function test_setting_the_role_an_administrator_already_holds_or_an_unknown_one_is_refused(): void
    {
        $this->admin('root@example.test');

        $this->artisan('identity:set-admin-role', ['email' => 'root@example.test', 'role' => 'super_admin'])
            ->expectsConfirmation('Give root@example.test the role super_admin?', 'yes')
            ->assertExitCode(1);
        $this->artisan('identity:set-admin-role', ['email' => 'root@example.test', 'role' => 'nobody'])
            ->expectsConfirmation('Give root@example.test the role nobody?', 'yes')
            ->assertExitCode(1);
        $this->artisan('identity:set-admin-role', ['email' => 'nobody@example.test', 'role' => 'super_admin'])
            ->expectsConfirmation('Give nobody@example.test the role super_admin?', 'yes')
            ->assertExitCode(1);
    }

    public function test_a_suspended_administrator_loses_every_session_and_cannot_sign_in_until_reactivated(): void
    {
        $this->admin('root@example.test');
        $other = $this->admin('other@example.test');
        $other->createToken('panel', ['*'], now()->addHours(12));

        $this->artisan('identity:suspend-admin', ['email' => 'other@example.test'])
            ->expectsConfirmation('Suspend other@example.test and end every session?', 'yes')
            ->assertExitCode(0);

        $this->assertSame('suspended', $other->refresh()->status);
        $this->assertSame(0, $other->tokens()->count());
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'other@example.test', 'password' => 'a-strong-password'])
            ->assertForbidden()
            ->assertJsonPath('type', 'urn:onesis:error:account-suspended');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'admin.suspended', 'subject_id' => $other->id]);

        $this->artisan('identity:suspend-admin', ['email' => 'other@example.test'])
            ->expectsConfirmation('Suspend other@example.test and end every session?', 'yes')
            ->assertExitCode(1);

        $this->artisan('identity:reactivate-admin', ['email' => 'other@example.test'])->assertExitCode(0);

        $this->assertSame('active', $other->refresh()->status);
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'other@example.test', 'password' => 'a-strong-password'])->assertOk();
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'admin.reactivated', 'subject_id' => $other->id]);
        $this->artisan('identity:reactivate-admin', ['email' => 'other@example.test'])->assertExitCode(1);
    }

    public function test_a_new_password_replaces_the_old_one_and_ends_every_session(): void
    {
        $admin = $this->admin('root@example.test');
        $admin->createToken('panel', ['*'], now()->addHours(12));

        $this->artisan('identity:reset-admin-password', ['email' => 'root@example.test'])
            ->expectsQuestion('New password (minimum 8 characters)', 'another-strong-password')
            ->expectsQuestion('Repeat the password', 'another-strong-password')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertTrue(Hash::check('another-strong-password', $admin->password));
        $this->assertSame(0, $admin->tokens()->count());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'admin.password_reset', 'subject_id' => $admin->id]);
        $this->assertStringNotContainsString('another-strong-password', (string) json_encode(DB::table('platform_audit_logs')->pluck('metadata')->all()));
    }

    public function test_a_new_password_must_match_its_repetition_and_follow_the_rules(): void
    {
        $admin = $this->admin('root@example.test');
        $hash = $admin->password;

        $this->artisan('identity:reset-admin-password', ['email' => 'root@example.test'])
            ->expectsQuestion('New password (minimum 8 characters)', 'another-strong-password')
            ->expectsQuestion('Repeat the password', 'something-else-entirely')
            ->assertExitCode(1);
        $this->artisan('identity:reset-admin-password', ['email' => 'root@example.test'])
            ->expectsQuestion('New password (minimum 8 characters)', 'short')
            ->expectsQuestion('Repeat the password', 'short')
            ->assertExitCode(1);

        $this->assertSame($hash, $admin->refresh()->password);
    }

    public function test_the_lists_show_permissions_roles_and_administrators(): void
    {
        $this->admin('root@example.test');
        $this->createRole();

        $this->artisan('identity:list-permissions')->expectsOutputToContain('probe.write')->assertExitCode(0);
        $this->artisan('identity:list-roles')->expectsOutputToContain('moderator')->assertExitCode(0);
        $this->artisan('identity:list-admins')->expectsOutputToContain('root@example.test')->assertExitCode(0);
    }

    public function test_the_operator_is_recorded_with_the_entry(): void
    {
        putenv('SUDO_USER=ops-person');

        try {
            $this->createRole();
        } finally {
            putenv('SUDO_USER');
        }

        $metadata = json_decode((string) DB::table('platform_audit_logs')->where('action', 'admin.role_created')->value('metadata'), true);
        $this->assertSame(['role' => 'moderator', 'permissions' => ['probe.read'], 'operator' => 'ops-person'], $metadata);
    }
}
