<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignedOutEverywhere;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;

/** Revokes every token of the signed-in account (user or admin), the one in use included. */
final readonly class SignOutEverywhere
{
    public function __construct(
        private AccessTokenIssuer $tokens,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function execute(Actor $actor, ?string $ipAddress, ?string $userAgent): void
    {
        $this->tokens->revokeAll($actor);

        $scope = $actor->isAdmin() ? AccountScope::Admin : AccountScope::User;
        $this->events->dispatch([new SignedOutEverywhere($scope, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
