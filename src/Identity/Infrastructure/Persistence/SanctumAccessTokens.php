<?php

namespace Src\Identity\Infrastructure\Persistence;

use Src\Identity\Domain\AccessTokens;

class SanctumAccessTokens implements AccessTokens
{
    public function issue(int $userId): string
    {
        return UserModel::query()->findOrFail($userId)->createToken('job-pilot')->plainTextToken;
    }

    public function revokeAll(int $userId): void
    {
        UserModel::query()->find($userId)?->tokens()->delete();
    }
}
