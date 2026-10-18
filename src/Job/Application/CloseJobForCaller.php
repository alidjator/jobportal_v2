<?php

namespace Src\Job\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\JobCloser;

/** The port of the Recruitment context for closing a filled job: the ordinary close. */
final readonly class CloseJobForCaller implements JobCloser
{
    public function __construct(private CloseJob $close) {}

    public function close(Actor $actor, int $jobId, ?string $ipAddress, ?string $userAgent): void
    {
        $this->close->execute($actor, $jobId, $ipAddress, $userAgent);
    }
}
