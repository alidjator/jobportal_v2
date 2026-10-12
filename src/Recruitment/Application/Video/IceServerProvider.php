<?php

namespace Src\Recruitment\Application\Video;

use DateTimeImmutable;

interface IceServerProvider
{
    /** @return list<IceServer> empty when no STUN or TURN server is configured */
    public function forUser(int $userId, DateTimeImmutable $expiresAt): array;
}
