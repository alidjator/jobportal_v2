<?php

namespace Src\Recruitment\Application\Video;

use DateTimeImmutable;

/** What a browser needs to join the room: the room, its pin, a token, the display name it must use, the address of the video server and the ICE servers. */
final readonly class VideoCredentials
{
    /** @param  list<IceServer>  $iceServers */
    public function __construct(
        public int $roomId,
        public string $pin,
        public string $token,
        public string $display,
        public string $websocketUrl,
        public array $iceServers,
        public DateTimeImmutable $expiresAt,
    ) {}
}
