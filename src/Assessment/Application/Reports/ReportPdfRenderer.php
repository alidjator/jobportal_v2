<?php

namespace Src\Assessment\Application\Reports;

/** Turns a signed report into the bytes of a PDF. Implemented in Infrastructure. */
interface ReportPdfRenderer
{
    public function render(ReportDocument $document): string;
}
