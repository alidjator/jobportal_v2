<?php

namespace Src\Company\Application\Admin;

/** How many companies hold each status, with a zero for a status no company holds, and the total of them all. */
final readonly class AdminCompanyStatusCounts
{
    /** @param  array<string, int>  $byStatus  keyed by the status value, one entry per `CompanyStatus` */
    public function __construct(public int $all, public array $byStatus) {}
}
