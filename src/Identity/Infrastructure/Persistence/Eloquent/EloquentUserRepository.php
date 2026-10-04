<?php

namespace Src\Identity\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Identity\Domain\AccountStatus;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\UserType;
use Src\Shared\Domain\Exceptions\StateConflict;

final class EloquentUserRepository implements UserRepository
{
    public function findById(int $id): ?User
    {
        $model = UserModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByEmail(string $email): ?User
    {
        $model = UserModel::query()->where('email', $email)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findDueForAnonymization(DateTimeImmutable $now, int $limit): array
    {
        $dueRequestedBefore = $now->modify('-'.User::DELETION_GRACE_PERIOD_DAYS.' days');

        return UserModel::query()
            ->where('status', AccountStatus::Deactivated->value)
            ->whereNotNull('deletion_requested_at')
            ->whereNull('anonymized_at')
            ->where('deletion_requested_at', '<=', $dueRequestedBefore)
            ->orderBy('deletion_requested_at')
            ->limit($limit)
            ->get()
            ->map(fn (UserModel $model): User => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function save(User $user): User
    {
        $model = $user->id === null ? new UserModel : UserModel::query()->findOrFail($user->id);

        $model->forceFill([
            'type' => $user->type->value,
            'name' => $user->name(),
            'email' => $user->email(),
            'password' => $user->passwordHash(),
            'email_verified_at' => $user->emailVerifiedAt(),
            'status' => $user->status()->value,
            'avatar_path' => $user->avatarPath(),
            'locale' => $user->locale(),
            'registration_campaign_code' => $user->registrationCampaignCode(),
            'last_login_at' => $user->lastLoginAt(),
            'deletion_requested_at' => $user->deletionRequestedAt(),
            'anonymized_at' => $user->anonymizedAt(),
        ]);

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            // Two registrations with the same email raced past the use case's check; the unique index decides.
            throw new StateConflict('An account with this email already exists.', 'email-already-registered');
        }

        return $this->toDomain($model);
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            $model->id,
            UserType::from($model->type),
            $model->name,
            $model->email,
            $model->password,
            $model->email_verified_at?->toDateTimeImmutable(),
            AccountStatus::from($model->status),
            $model->avatar_path,
            $model->locale,
            $model->registration_campaign_code,
            $model->last_login_at?->toDateTimeImmutable(),
            $model->deletion_requested_at?->toDateTimeImmutable(),
            $model->anonymized_at?->toDateTimeImmutable(),
        );
    }
}
