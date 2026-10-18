<?php

namespace Src\Recruitment\Application;

/** The export of hires as text cells: the header line and one line per hire, ready for a CSV writer. The rows are produced lazily, as the writer iterates. */
final readonly class HiresExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     */
    public function __construct(public string $from, public string $to, public array $headers, public iterable $rows) {}
}
