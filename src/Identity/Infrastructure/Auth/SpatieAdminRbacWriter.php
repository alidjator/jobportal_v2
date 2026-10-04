<?php

namespace Src\Identity\Infrastructure\Auth;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Application\Admin\AdminRbacWriter;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class SpatieAdminRbacWriter implements AdminRbacWriter
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function syncPermissions(array $definitions): void
    {
        foreach ($definitions as $definition) {
            $permission = Permission::query()->firstOrNew(['name' => $definition->name, 'guard_name' => AdminModel::GUARD]);
            $permission->setAttribute('group_name', $definition->group);
            $permission->save();
        }

        $this->registrar->forgetCachedPermissions();
    }

    public function syncRole(string $role, array $permissionNames): void
    {
        Role::findOrCreate($role, AdminModel::GUARD)->syncPermissions($permissionNames);

        $this->registrar->forgetCachedPermissions();
    }

    public function assignRole(int $adminId, string $role): void
    {
        $roleModel = Role::query()->where('name', $role)->where('guard_name', AdminModel::GUARD)->first()
            ?? throw EntityNotFound::of('Role', $role);

        AdminModel::query()->findOrFail($adminId)->assignRole($roleModel);
    }
}
