<?php

namespace Src\Notification\Domain;

interface DeviceTokenRepository
{
    /**
     * Idempotent (firstOrCreate). `$userId` null berarti token anonim.
     *
     * @return array<string, mixed> baris device token
     */
    public function store(?int $userId, string $token): array;

    /** @return list<string> */
    public function tokensForUser(int $userId): array;

    /** @return list<string> */
    public function allTokens(): array;
}
