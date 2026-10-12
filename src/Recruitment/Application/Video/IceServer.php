<?php

namespace Src\Recruitment\Application\Video;

/** A STUN or TURN server for the browser; a TURN server comes with time-limited credentials. */
final readonly class IceServer
{
    /** @param  list<string>  $urls */
    public function __construct(public array $urls, public ?string $username = null, public ?string $credential = null) {}
}
