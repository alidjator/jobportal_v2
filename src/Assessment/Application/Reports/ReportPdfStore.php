<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\StoredReportPdf;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Keeps the PDF of a signed report: rendered once, written to the private disk under `assessment/reports/{report}/{number}.pdf` and hashed (SHA-256), so a company downloads the same bytes every
 * time and the hash the public check answers with stays valid. It is made after the signature (a queued listener) and, for a report whose file is not there yet, at the first download. Each
 * revision has a file of its own; the file of the version a revision replaces stays. A report whose candidate was erased has no file and cannot get one.
 */
final readonly class ReportPdfStore
{
    public function __construct(
        private ReportRepository $reports,
        private ReportDocumentBuilder $documents,
        private ReportPdfRenderer $renderer,
        private FileStorage $files,
    ) {}

    /**
     * The bytes of the report's PDF, rendered and stored first when there is no file yet.
     *
     * @throws EntityNotFound
     * @throws StateConflict `report-not-signed` or `report-erased`
     */
    public function contents(Report $report): string
    {
        if (! $report->isSigned() || $report->number === null || $report->signedAt === null) {
            throw new StateConflict('The report is not signed yet.', 'report-not-signed');
        }

        $stored = $this->reports->storedPdf((int) $report->id);
        $kept = $stored === null ? null : $this->files->get($stored->path);

        if ($kept !== null) {
            return $kept;
        }

        if ($report->body === '') {
            throw new StateConflict('The report was erased with the candidate\'s account.', 'report-erased');
        }

        $contents = $this->renderer->render($this->documents->build($report));
        $path = sprintf('assessment/reports/%d/%s.pdf', (int) $report->id, str_replace('/', '-', $report->number));

        $this->files->put($path, $contents);
        $this->reports->storePdf((int) $report->id, new StoredReportPdf($path, hash('sha256', $contents)));

        return $contents;
    }

    /** The name the file is offered under: the number holds slashes, so they become dashes. */
    public function fileName(Report $report): string
    {
        return 'report-'.str_replace('/', '-', (string) $report->number).'.pdf';
    }
}
