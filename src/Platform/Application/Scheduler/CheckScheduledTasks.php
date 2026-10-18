<?php

namespace Src\Platform\Application\Scheduler;

use Src\Platform\Application\PlatformSettings;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;

/**
 * Emails `platform.operations_email` once when a task turns late or failing and once when it recovers (`alerted_at` remembers an open alert). Tasks that turned in the same
 * check share one email. With no address set nothing is sent and no alert is marked open, so an alert goes out once an address is set while the problem lasts.
 * Tasks that left the schedule are forgotten.
 */
final readonly class CheckScheduledTasks
{
    public function __construct(
        private ReadScheduler $scheduler,
        private ScheduledTaskRunRepository $runs,
        private ScheduledTasks $scheduled,
        private SchedulerAlerts $alerts,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /** @return array{raised: int, recovered: int} */
    public function execute(): array
    {
        $this->runs->forgetExcept($this->scheduled->names());

        $email = trim($this->settings->text(PlatformSettings::OPERATIONS_EMAIL));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['raised' => 0, 'recovered' => 0];
        }

        $raised = $recovered = [];

        foreach ($this->scheduler->execute()->tasks as $status) {
            if ($status->unhealthy() && $status->alertedAt === null) {
                $raised[] = $status;
            } elseif (! $status->unhealthy() && $status->alertedAt !== null) {
                $recovered[] = $status->task;
            }
        }

        $now = $this->clock->now();

        if ($raised !== []) {
            $this->alerts->raised($email, $raised);

            foreach ($raised as $status) {
                $this->runs->markAlerted($status->task, $now);
            }
        }

        if ($recovered !== []) {
            $this->alerts->recovered($email, $recovered);

            foreach ($recovered as $task) {
                $this->runs->markAlerted($task, null);
            }
        }

        return ['raised' => count($raised), 'recovered' => count($recovered)];
    }
}
