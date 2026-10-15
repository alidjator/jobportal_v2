<?php

namespace Src\Job\Application\Reports;

use Src\Job\Domain\JobReportRepository;
use Src\Shared\Application\Accounts\ErasureStep;

/** Clears the reporter and the note of the reports of a user whose account was anonymised; the reports stay for the admins' decisions. Running it again does nothing. */
final readonly class EraseJobReports implements ErasureStep
{
    public function __construct(private JobReportRepository $reports) {}

    public function name(): string
    {
        return 'job-reports';
    }

    public function execute(int $userId): void
    {
        $this->reports->eraseReporter($userId);
    }
}
