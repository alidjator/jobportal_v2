<?php

namespace Src\Workforce\Domain;

interface RequisitionCounters
{
    /**
     * The next running number of a series of the company (`RequisitionNumberFormat::series`), 1 for the first. It must run inside the transaction that stores the requisition, so a
     * requisition that is not stored gives its number back, and two requisitions never get the same one.
     */
    public function next(int $companyId, string $series): int;
}
