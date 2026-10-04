<?php

namespace Src\Identity\Domain;

/** Links between a user and an account at a social provider (`identity_social_accounts`; only `google` so far). */
interface SocialAccountRepository
{
    public const string GOOGLE = 'google';

    public function findUserId(string $provider, string $providerUserId): ?int;

    /** Links the provider account to the user. At most one link per provider and user, and one user per provider account. */
    public function link(int $userId, string $provider, string $providerUserId, ?string $email): void;
}
