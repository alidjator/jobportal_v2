<?php

namespace Src\Newsletter\Application;

/** Puts newsletter work on the `bulk` queue. */
interface NewsletterQueue
{
    /** Walks the confirmed subscribers of a sending issue and queues their deliveries. */
    public function dispatchIssue(int $issueId): void;

    public function queueDelivery(int $deliveryId): void;
}
