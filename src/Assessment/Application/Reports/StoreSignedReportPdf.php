<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Domain\Exceptions\StateConflict;

/** After a report is signed or revised, renders its PDF and keeps it (`ReportPdfStore`). A report that is gone, not signed or erased is skipped; the download makes the file when this has not run yet. */
final readonly class StoreSignedReportPdf
{
    public function __construct(private ReportRepository $reports, private ReportPdfStore $store) {}

    public function execute(int $reportId): void
    {
        $report = $this->reports->find($reportId);

        if ($report === null) {
            return;
        }

        try {
            $this->store->contents($report);
        } catch (StateConflict) {
            // Not signed (a draft) or erased with the candidate's account: there is no file to keep.
        }
    }
}
