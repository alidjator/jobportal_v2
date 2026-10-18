<?php

namespace Src\Platform\Infrastructure\Listeners;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Psr\Log\LoggerInterface;
use Src\Platform\Application\Scheduler\ScheduledTaskRecorder;
use Src\Platform\Infrastructure\Scheduler\ScheduledTaskName;
use Throwable;

/**
 * Feeds the scheduler watch. A command that exits with a failure code raises both a finish and a failure event: only the failure counts. A problem in the watch itself
 * is logged and never stops the scheduler.
 */
final readonly class RecordScheduledTaskEvents
{
    public function __construct(private ScheduledTaskRecorder $recorder, private LoggerInterface $logger) {}

    public function onStarting(ScheduledTaskStarting $event): void
    {
        $this->guard(fn () => $this->recorder->started(ScheduledTaskName::of($event->task), $event->task->expression));
    }

    public function onFinished(ScheduledTaskFinished $event): void
    {
        if ($event->task->exitCode !== null && $event->task->exitCode !== 0) {
            return;
        }

        $this->guard(fn () => $this->recorder->finished(ScheduledTaskName::of($event->task), $event->task->expression));
    }

    public function onFailed(ScheduledTaskFailed $event): void
    {
        $this->guard(fn () => $this->recorder->failed(ScheduledTaskName::of($event->task), $event->task->expression, $event->exception::class.': '.$event->exception->getMessage()));
    }

    private function guard(callable $record): void
    {
        try {
            $record();
        } catch (Throwable $e) {
            $this->logger->warning('The scheduler watch could not record a task event.', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }
}
