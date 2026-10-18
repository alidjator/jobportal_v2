<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/**
 * Which hires an export selects: the applications of the company now in `hired` whose last move into `hired` falls in `[$from, $until)` (UTC instants, the
 * bounds of whole Jakarta days). `$jobIds` limits the jobs (null: every job of the company); on the jobs of `$boundJobIds` (the member is a bound hiring manager)
 * only applications that carry the marker count.
 */
final readonly class HireExportFilter
{
    /**
     * @param  list<int>|null  $jobIds
     * @param  list<int>  $boundJobIds
     */
    public function __construct(
        public int $companyId,
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
        public ?array $jobIds,
        public array $boundJobIds,
    ) {}
}
