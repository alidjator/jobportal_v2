<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/** The export of a job's applicants as text cells: the header line and one line per application, ready for a CSV writer. The rows are produced lazily, as the writer iterates. */
final readonly class ApplicantExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     */
    public function __construct(public int $jobId, public DateTimeImmutable $exportedAt, public array $headers, public iterable $rows) {}
}
