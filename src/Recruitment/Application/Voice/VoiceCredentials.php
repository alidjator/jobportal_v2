<?php

namespace Src\Recruitment\Application\Voice;

use DateTimeImmutable;
use Src\Recruitment\Application\Video\IceServer;

/** What a team member's browser needs to join the call: its SIP account, the server to register with, the number to dial to enter the interview, and the ICE servers. */
final readonly class VoiceCredentials
{
    /** @param  list<IceServer>  $iceServers */
    public function __construct(
        public string $username,
        public string $password,
        public string $sipDomain,
        public string $websocketUrl,
        public string $extension,
        public array $iceServers,
        public DateTimeImmutable $expiresAt,
    ) {}
}
