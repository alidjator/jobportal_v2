<?php

namespace Src\Job\Infrastructure;

use Src\Job\Application\Views\JobViewRecorder;
use Src\Job\Infrastructure\Jobs\WriteJobView;

final class QueuedJobViewRecorder implements JobViewRecorder
{
    public function record(int $jobId, string $viewerKey, string $day): void
    {
        WriteJobView::dispatch($jobId, $viewerKey, $day);
    }
}
