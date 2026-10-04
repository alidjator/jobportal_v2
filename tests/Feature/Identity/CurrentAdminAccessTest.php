<?php

namespace Tests\Feature\Identity;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\FeatureTestCase;

final class CurrentAdminAccessTest extends FeatureTestCase
{
    private function adminWithToken(): array
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = AdminModel::query()->create(['name' => 'Root', 'email' => 'root@example.test', 'password' => 'hash', 'status' => 'active']);

        return [$admin, $admin->createToken('console', ['*'], now()->addHour())->plainTextToken];
    }

    private function permission(string $name): Permission
    {
        return Permission::create(['name' => $name, 'guard_name' => AdminModel::GUARD]);
    }

    public function test_an_admin_without_roles_has_empty_lists(): void
    {
        [$admin, $token] = $this->adminWithToken();

        $this->withToken($token)->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $admin->id, 'name' => 'Root', 'email' => 'root@example.test', 'status' => 'active', 'roles' => [], 'permissions' => []]]);
    }

    public function test_roles_and_effective_permissions_are_sorted_and_unique(): void
    {
        [$admin, $token] = $this->adminWithToken();

        $view = $this->permission('audit_logs.view');
        $update = $this->permission('settings.update');
        $moderate = $this->permission('reviews.moderate');

        $auditor = Role::create(['name' => 'auditor', 'guard_name' => AdminModel::GUARD]);
        $auditor->givePermissionTo($view, $update);
        $moderator = Role::create(['name' => 'moderator', 'guard_name' => AdminModel::GUARD]);
        $moderator->givePermissionTo($moderate, $view);

        $admin->assignRole($moderator, $auditor);
        $admin->givePermissionTo($update);

        $this->withToken($token)->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['auditor', 'moderator'])
            ->assertJsonPath('data.permissions', ['audit_logs.view', 'reviews.moderate', 'settings.update']);
    }
}
