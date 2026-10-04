<?php

namespace Src\Notification\Application\DeviceTokens;

use Src\Shared\Application\Actor;

/** Removes the push token of a device the actor no longer uses. Idempotent, and a token that is not the actor's is left alone without saying so. */
final readonly class UnregisterDeviceToken
{
    public function __construct(private DeviceTokenRepository $tokens) {}

    public function execute(Actor $actor, string $token): void
    {
        $this->tokens->delete((int) $actor->id, $token);
    }
}
