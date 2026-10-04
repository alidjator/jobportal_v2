<?php

namespace Src\Identity\Application\Auth;

use Src\Shared\Application\Actor;

/** Signs out by revoking the token the request was made with. Other devices keep their own tokens. */
final readonly class SignOut
{
    public function __construct(private AccessTokenIssuer $tokens) {}

    public function execute(Actor $actor, int $tokenId): void
    {
        $this->tokens->revoke($actor, $tokenId);
    }
}
