<?php

namespace Src\Company\Application\Activity;

use DateTimeImmutable;

/** The company activity log as text cells: the header line and one line per entry, ready for a CSV writer. The rows are produced lazily, as the writer iterates. */
final readonly class CompanyActivityExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     */
    public function __construct(public int $companyId, public DateTimeImmutable $exportedAt, public array $headers, public iterable $rows) {}
}
