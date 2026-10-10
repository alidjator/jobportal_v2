<?php

namespace Src\Identity\Application\Auth;

use DateTimeImmutable;
use Src\Shared\Application\Actor;

interface AccessTokenIssuer
{
    /** Creates a token for a public user and returns the plain text token (shown to the client only once). */
    public function issueForUser(int $userId, string $deviceName, DateTimeImmutable $expiresAt): string;

    /** Creates a token for an administrator and returns the plain text token (shown to the client only once). */
    public function issueForAdmin(int $adminId, string $deviceName, DateTimeImmutable $expiresAt): string;

    /** Revokes one token of the actor's own account. A token of another account is left untouched. */
    public function revoke(Actor $actor, int $tokenId): void;

    /** Revokes every token of a public user, on every device. */
    public function revokeAllForUser(int $userId): void;

    /** Revokes every token of the actor's own account, the one in use included. */
    public function revokeAll(Actor $actor): void;

    /** Revokes every token of the actor's own account except the one in use. */
    public function revokeOthers(Actor $actor, int $keepTokenId): void;
}
