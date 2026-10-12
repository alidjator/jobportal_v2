<?php

namespace Src\Recruitment\Application\Video;

/** The answer to a join: `ready` with credentials, or `waiting` in the lobby (job seeker only) without. */
final readonly class VideoJoinResult
{
    public const string READY = 'ready';

    public const string WAITING = 'waiting';

    public function __construct(public string $status, public ?VideoCredentials $credentials = null) {}
}
