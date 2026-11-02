<?php

namespace Src\Assessment\Application\Reports;

/** The public address where a reader checks a report number (the Career page "Verifikasi laporan"); printed in the footer of the PDF. Provided by Infrastructure from the configuration. */
interface ReportVerificationLinks
{
    /** The address for the number, or null when the platform has no verification page configured (the footer then shows the number only). */
    public function forNumber(string $number): ?string;
}
