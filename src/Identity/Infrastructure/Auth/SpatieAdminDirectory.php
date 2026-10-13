<?php

namespace Src\Identity\Infrastructure\Auth;

use Spatie\Permission\Models\Role;
use Src\Identity\Application\Admin\AdminDirectory;
use Src\Identity\Application\Admin\AdminRoleSummary;
use Src\Identity\Application\Admin\AdminSummary;
use Src\Identity\Domain\AccountStatus;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;

final class SpatieAdminDirectory implements AdminDirectory
{
    public function roles(): array
    {
        return Role::query()->where('guard_name', AdminModel::GUARD)->orderBy('name')->with('permissions')->get()
            ->map(fn (Role $role): AdminRoleSummary => $this->summary($role))
            ->values()->all();
    }

    public function role(string $name): ?AdminRoleSummary
    {
        $role = Role::query()->where('guard_name', AdminModel::GUARD)->where('name', $name)->with('permissions')->first();

        return $role === null ? null : $this->summary($role);
    }

    public function admins(): array
    {
        return AdminModel::query()->with('roles')->orderBy('email')->get()
            ->map(fn (AdminModel $admin): AdminSummary => new AdminSummary(
                (int) $admin->id,
                (string) $admin->name,
                (string) $admin->email,
                $admin->roles->pluck('name')->sort()->first(),
                AccountStatus::from((string) $admin->status),
                $admin->two_factor_confirmed_at !== null,
                $admin->last_login_at,
            ))
            ->values()->all();
    }

    public function roleOf(int $adminId): ?string
    {
        return AdminModel::query()->findOrFail($adminId)->getRoleNames()->sort()->first();
    }

    public function countActiveAdminsWithRole(string $role): int
    {
        if (! Role::query()->where('guard_name', AdminModel::GUARD)->where('name', $role)->exists()) {
            return 0;
        }

        return AdminModel::query()->role($role)->where('status', AccountStatus::Active->value)->count();
    }

    private function summary(Role $role): AdminRoleSummary
    {
        return new AdminRoleSummary(
            (string) $role->name,
            $role->permissions->pluck('name')->sort()->values()->all(),
            AdminModel::query()->role($role->name)->count(),
        );
    }
}
