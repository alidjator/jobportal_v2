<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\ReportVerification;

/**
 * The public check of a report number (`GET verify/assessment-reports?number=`, no sign-in): who issued it, when, which revision, whether a later one replaced it and the SHA-256 of the stored PDF.
 * Null for a number nobody issued. Nothing about the candidate, the company, the job, the scales or the recommendation.
 */
final readonly class VerifyReport
{
    public function __construct(private ReportRepository $reports) {}

    public function execute(string $number): ?ReportVerification
    {
        return $this->reports->verification(strtoupper(trim($number)));
    }
}
