<?php

namespace Src\Notification\Application\DeviceTokens;

use DateTimeImmutable;

interface DeviceTokenRepository
{
    /**
     * Creates the token for the user. A token that already exists is updated and given to this user, whoever owned it before: the
     * physical device is now used with this account.
     */
    public function assign(int $userId, string $token, DevicePlatform $platform, DateTimeImmutable $at): void;

    /** Deletes the user's tokens beyond the `$keep` most recently used ones (ties broken by the newest id). */
    public function trim(int $userId, int $keep): void;

    /** Deletes the token when it belongs to the user; does nothing otherwise. */
    public function delete(int $userId, string $token): void;
}
