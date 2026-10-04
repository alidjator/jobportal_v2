<?php

namespace Src\Identity\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use Src\Identity\Domain\AccountStatus;
use Src\Identity\Domain\Admin;
use Src\Identity\Domain\AdminRepository;
use Src\Shared\Domain\Exceptions\StateConflict;

final class EloquentAdminRepository implements AdminRepository
{
    public function findById(int $id): ?Admin
    {
        $model = AdminModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByEmail(string $email): ?Admin
    {
        $model = AdminModel::query()->where('email', $email)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(Admin $admin): Admin
    {
        $model = $admin->id === null ? new AdminModel : AdminModel::query()->findOrFail($admin->id);

        $model->forceFill([
            'name' => $admin->name(),
            'email' => $admin->email(),
            'password' => $admin->passwordHash(),
            'email_verified_at' => $admin->emailVerifiedAt(),
            'status' => $admin->status()->value,
            'avatar_path' => $admin->avatarPath(),
            'last_login_at' => $admin->lastLoginAt(),
        ]);

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('An administrator with this email already exists.', 'email-already-registered');
        }

        return $this->toDomain($model);
    }

    private function toDomain(AdminModel $model): Admin
    {
        return new Admin(
            $model->id,
            $model->name,
            $model->email,
            $model->password,
            $model->email_verified_at?->toDateTimeImmutable(),
            AccountStatus::from($model->status),
            $model->avatar_path,
            $model->last_login_at?->toDateTimeImmutable(),
        );
    }
}
