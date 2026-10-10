<?php

namespace Src\Identity\Infrastructure\Auth;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;
use Src\Identity\Application\Auth\AccessTokenIssuer;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;

final class SanctumAccessTokenIssuer implements AccessTokenIssuer
{
    public function issueForUser(int $userId, string $deviceName, DateTimeImmutable $expiresAt): string
    {
        return UserModel::query()->findOrFail($userId)->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;
    }

    public function issueForAdmin(int $adminId, string $deviceName, DateTimeImmutable $expiresAt): string
    {
        return AdminModel::query()->findOrFail($adminId)->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;
    }

    public function revokeAllForUser(int $userId): void
    {
        UserModel::query()->findOrFail($userId)->tokens()->delete();
    }

    public function revoke(Actor $actor, int $tokenId): void
    {
        $this->tokensOf($actor)->whereKey($tokenId)->delete();
    }

    public function revokeAll(Actor $actor): void
    {
        $this->tokensOf($actor)->delete();
    }

    public function revokeOthers(Actor $actor, int $keepTokenId): void
    {
        $this->tokensOf($actor)->whereKeyNot($keepTokenId)->delete();
    }

    /** @return Builder<PersonalAccessToken> */
    private function tokensOf(Actor $actor): Builder
    {
        $owner = match ($actor->kind) {
            ActorKind::JobSeeker, ActorKind::CompanyMember => UserModel::class,
            ActorKind::Admin => AdminModel::class,
            ActorKind::System => throw new InvalidArgumentException('The system actor has no tokens.'),
        };

        return PersonalAccessToken::query()
            ->where('tokenable_type', (new $owner)->getMorphClass())
            ->where('tokenable_id', $actor->id);
    }
}
