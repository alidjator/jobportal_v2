<?php

namespace Src\Assessment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Assessment\Application\Reports\StoreSignedReportPdf;
use Src\Assessment\Domain\Events\ReportRevised;
use Src\Assessment\Domain\Events\ReportSigned;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Renders and keeps the PDF of a report after it was signed or revised, on the `default` queue, retried 3 times with a growing delay. A failure never undoes the signature: the first download renders the file. */
final class StoreReportPdfListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly StoreSignedReportPdf $store) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function signed(ReportSigned $event): void
    {
        $this->store->execute($event->reportId);
    }

    public function revised(ReportRevised $event): void
    {
        $this->store->execute($event->reportId);
    }
}
