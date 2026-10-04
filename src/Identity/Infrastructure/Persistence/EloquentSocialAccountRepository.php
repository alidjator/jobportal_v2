<?php

namespace Src\Identity\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Identity\Domain\SocialAccountRepository;
use Src\Shared\Domain\Exceptions\StateConflict;

final readonly class EloquentSocialAccountRepository implements SocialAccountRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function findUserId(string $provider, string $providerUserId): ?int
    {
        $userId = $this->connection->table('identity_social_accounts')
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->value('user_id');

        return $userId === null ? null : (int) $userId;
    }

    public function link(int $userId, string $provider, string $providerUserId, ?string $email): void
    {
        try {
            $this->connection->table('identity_social_accounts')->insert([
                'user_id' => $userId,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'email' => $email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('A different Google account is already linked to this account.', 'social-account-conflict');
        }
    }
}
