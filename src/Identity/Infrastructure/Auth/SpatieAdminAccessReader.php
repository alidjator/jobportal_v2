<?php

namespace Src\Identity\Infrastructure\Auth;

use Src\Identity\Application\Account\AdminAccess;
use Src\Identity\Application\Account\AdminAccessReader;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;

final class SpatieAdminAccessReader implements AdminAccessReader
{
    public function forAdmin(int $adminId): AdminAccess
    {
        $admin = AdminModel::query()->findOrFail($adminId);

        return new AdminAccess(
            $admin->getRoleNames()->sort()->values()->all(),
            $admin->getAllPermissions()->pluck('name')->unique()->sort()->values()->all(),
        );
    }
}
