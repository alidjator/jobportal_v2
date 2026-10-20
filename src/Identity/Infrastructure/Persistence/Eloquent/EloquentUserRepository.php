<?php

namespace Src\Identity\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Identity\Domain\AccountStatus;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Infrastructure\Persistence\TextSearch;

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

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $users = [];

        foreach (UserModel::query()->whereIn('id', $ids)->get() as $model) {
            $users[(int) $model->id] = $this->toDomain($model);
        }

        return $users;
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

    public function findJobSeekerIdsFirstSignedInBefore(DateTimeImmutable $cutoff, int $afterId, int $limit): array
    {
        return UserModel::query()
            ->where('type', UserType::JobSeeker->value)
            ->where('status', AccountStatus::Active->value)
            ->whereNull('anonymized_at')
            ->whereNotNull('email_verified_at')
            ->where('first_login_at', '<=', $cutoff)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function findHeldIds(array $userIds): array
    {
        return UserModel::query()
            ->whereIn('id', $userIds)
            ->whereNotNull('imported_at')
            ->whereNull('first_login_at')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function search(?string $search, PageRequest $request): Page
    {
        $query = UserModel::query()->whereNull('anonymized_at');

        if ($search !== null) {
            $query->where(fn (Builder $match) => TextSearch::contains(TextSearch::contains($match->getQuery(), 'name', $search), 'email', $search, 'or'));
        }

        $total = (clone $query)->count();
        $models = $query->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page($models->map(fn (UserModel $model): User => $this->toDomain($model))->values()->all(), $total, $request);
    }

    public function findImportedAwaitingSignIn(UserType $type, int $afterId, int $limit, ?array $emails = null): array
    {
        return UserModel::query()
            ->where('type', $type->value)
            ->whereNotNull('imported_at')
            ->whereNull('first_login_at')
            ->whereNull('anonymized_at')
            ->when($emails !== null, fn (Builder $query) => $query->whereIn('email', $emails))
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (UserModel $model): User => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function countImported(UserType $type, bool $signedIn): int
    {
        return UserModel::query()
            ->where('type', $type->value)
            ->whereNotNull('imported_at')
            ->whereNull('anonymized_at')
            ->when($signedIn, fn (Builder $query) => $query->whereNotNull('first_login_at'), fn (Builder $query) => $query->whereNull('first_login_at'))
            ->count();
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
            'first_login_at' => $user->firstLoginAt(),
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
            $model->first_login_at?->toDateTimeImmutable(),
            $model->imported_at?->toDateTimeImmutable(),
        );
    }
}
