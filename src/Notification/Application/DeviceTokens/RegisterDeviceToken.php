<?php

namespace Src\Notification\Application\DeviceTokens;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;

/**
 * Registers the push token of the actor's device. Idempotent: registering a known token only refreshes its last-used time. A token
 * registered by another user moves to this one. A user keeps at most 10 tokens; an eleventh replaces the least recently used.
 */
final readonly class RegisterDeviceToken
{
    public const int MAX_TOKENS_PER_USER = 10;

    public function __construct(
        private DeviceTokenRepository $tokens,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    public function execute(Actor $actor, string $token, DevicePlatform $platform): void
    {
        $this->transactions->run(function () use ($actor, $token, $platform): void {
            $this->tokens->assign((int) $actor->id, $token, $platform, $this->clock->now());
            $this->tokens->trim((int) $actor->id, self::MAX_TOKENS_PER_USER);
        });
    }
}
