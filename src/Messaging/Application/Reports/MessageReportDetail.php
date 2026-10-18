<?php

namespace Src\Messaging\Application\Reports;

use Src\Messaging\Domain\MessageReport;

/** A report with the two sides of its conversation, for the admin who decides it. */
final readonly class MessageReportDetail
{
    public function __construct(
        public MessageReport $report,
        public int $companyId,
        public string $companyName,
        public int $jobSeekerUserId,
        public string $jobSeekerName,
    ) {}
}
