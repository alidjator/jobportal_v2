<?php

namespace Src\Messaging\Application\Reports;

use Src\Messaging\Domain\MessageReportRepository;
use Src\Shared\Application\Clock;

/** Clears the message snapshots of the reports decided more than 180 days ago; the report itself (reason, decision) stays. */
final readonly class PurgeReportSnapshots
{
    public const int KEEP_DAYS = 180;

    public function __construct(private MessageReportRepository $reports, private Clock $clock) {}

    public function execute(): int
    {
        return $this->reports->clearSnapshotsDecidedBefore($this->clock->now()->modify('-'.self::KEEP_DAYS.' days'));
    }
}
