<?php

namespace Src\Identity\Infrastructure\Persistence;

use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Shared\Application\Accounts\AdminAccounts;
use Src\Shared\Application\Accounts\AdminSummary;

final class EloquentAdminAccounts implements AdminAccounts
{
    private const string ACTIVE = 'active';

    public function holders(string $permission): array
    {
        try {
            $admins = AdminModel::query()->permission($permission)->where('status', self::ACTIVE)->orderBy('name')->orderBy('id')->get();
        } catch (PermissionDoesNotExist) {
            return [];
        }

        return $admins->map(static fn (AdminModel $admin): AdminSummary => new AdminSummary((int) $admin->id, $admin->name))->all();
    }

    public function holds(int $adminId, string $permission): bool
    {
        $admin = AdminModel::query()->where('status', self::ACTIVE)->find($adminId);

        if (! $admin instanceof AdminModel) {
            return false;
        }

        try {
            return $admin->hasPermissionTo($permission, AdminModel::GUARD);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function summaries(array $adminIds): array
    {
        if ($adminIds === []) {
            return [];
        }

        $summaries = [];

        foreach (AdminModel::query()->whereIn('id', $adminIds)->get() as $admin) {
            $summaries[(int) $admin->id] = new AdminSummary((int) $admin->id, $admin->name);
        }

        return $summaries;
    }
}
