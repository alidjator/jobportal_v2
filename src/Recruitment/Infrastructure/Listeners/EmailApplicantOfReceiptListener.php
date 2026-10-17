<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\EmailApplicantOfReceipt;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Shared\Infrastructure\Queue\QueueName;

/** A queued step on the `default` queue, retried 3 times with a growing delay, after the application was saved: the receipt email. A failure here never undoes an application. */
final class EmailApplicantOfReceiptListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly EmailApplicantOfReceipt $receipt) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ApplicationSubmitted $event): void
    {
        $this->receipt->handle($event);
    }
}
