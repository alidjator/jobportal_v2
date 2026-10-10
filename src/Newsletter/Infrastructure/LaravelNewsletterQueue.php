<?php

namespace Src\Newsletter\Infrastructure;

use Src\Newsletter\Application\NewsletterQueue;
use Src\Newsletter\Infrastructure\Jobs\DispatchNewsletterIssueJob;
use Src\Newsletter\Infrastructure\Jobs\SendNewsletterDeliveryJob;

final readonly class LaravelNewsletterQueue implements NewsletterQueue
{
    public function dispatchIssue(int $issueId): void
    {
        DispatchNewsletterIssueJob::dispatch($issueId);
    }

    public function queueDelivery(int $deliveryId): void
    {
        SendNewsletterDeliveryJob::dispatch($deliveryId);
    }
}
