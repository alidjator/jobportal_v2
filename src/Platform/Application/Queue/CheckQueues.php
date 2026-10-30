<?php

namespace Src\Platform\Application\Queue;

use Src\Platform\Application\PlatformSettings;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;

/**
 * Emails `platform.operations_email` about the queues. Failed jobs: when a queue has failures newer than the last email about it, at most once an hour per queue. Stalled:
 * once when the oldest due job of a queue waits more than 10 minutes and once when the queue moves again. Queues that turned in the same check share one email. With no
 * address set nothing is sent and nothing is marked, so an alert goes out once an address is set while the problem lasts.
 */
final readonly class CheckQueues
{
    private const string FAILED_EVERY = '-1 hour';

    public function __construct(
        private QueueHealth $health,
        private QueueAlertRepository $state,
        private QueueAlerts $alerts,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /** @return array{failed: int, stalled: int, recovered: int} the number of queues in each email */
    public function execute(): array
    {
        $email = trim($this->settings->text(PlatformSettings::OPERATIONS_EMAIL));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['failed' => 0, 'stalled' => 0, 'recovered' => 0];
        }

        $now = $this->clock->now();
        $known = [];

        foreach ($this->state->all() as $alert) {
            $known[$alert->kind->value][$alert->queue] = $alert;
        }

        $failed = [];

        foreach ($this->health->failedQueues() as $queue) {
            $alert = $known[QueueAlertKind::Failed->value][$queue] ?? null;

            if ($alert?->alertedAt !== null && $alert->alertedAt > $now->modify(self::FAILED_EVERY)) {
                continue;
            }

            $jobs = $this->health->failedSince($queue, $alert->lastFailedJobId ?? 0);

            if ($jobs !== null) {
                $failed[] = $jobs;
            }
        }

        $stalled = $raised = [];

        foreach ($this->health->queues($now) as $status) {
            if (! $status->stalled()) {
                continue;
            }

            $stalled[$status->queue] = true;

            if (! ($known[QueueAlertKind::Stalled->value][$status->queue] ?? null)?->open()) {
                $raised[] = $status;
            }
        }

        $recovered = [];

        foreach ($known[QueueAlertKind::Stalled->value] ?? [] as $queue => $alert) {
            if ($alert->open() && ! isset($stalled[$queue])) {
                $recovered[] = (string) $queue;
            }
        }

        if ($failed !== []) {
            $this->alerts->failed($email, $failed);

            foreach ($failed as $jobs) {
                $this->state->markAlerted(QueueAlertKind::Failed, $jobs->queue, $now, $jobs->newestId);
            }
        }

        if ($raised !== []) {
            $this->alerts->stalled($email, $raised);

            foreach ($raised as $status) {
                $this->state->markAlerted(QueueAlertKind::Stalled, $status->queue, $now);
            }
        }

        if ($recovered !== []) {
            $this->alerts->recovered($email, $recovered);

            foreach ($recovered as $queue) {
                $this->state->markRecovered(QueueAlertKind::Stalled, $queue, $now);
            }
        }

        return ['failed' => count($failed), 'stalled' => count($raised), 'recovered' => count($recovered)];
    }
}
