<?php

namespace Src\Platform\Application\Queue;

/** Tells the operations address about the queues. One email per call, listing every queue of the call. */
interface QueueAlerts
{
    /** @param  list<FailedJobs>  $queues */
    public function failed(string $email, array $queues): void;

    /** @param  list<QueueStatus>  $queues queues that turned stalled */
    public function stalled(string $email, array $queues): void;

    /** @param  list<string>  $queues names of queues that are moving again */
    public function recovered(string $email, array $queues): void;
}
