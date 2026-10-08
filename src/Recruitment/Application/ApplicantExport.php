<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/** The export of a job's applicants as text cells: the header line and one line per application, ready for a CSV writer. */
final readonly class ApplicantExport
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public function __construct(public int $jobId, public DateTimeImmutable $exportedAt, public array $headers, public array $rows) {}
}
