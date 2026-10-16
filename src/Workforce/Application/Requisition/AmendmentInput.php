<?php

namespace Src\Workforce\Application\Requisition;

/** A requested change of an open requisition: the new number of hires and/or the new salary range (a null is left as it is), with the reason. */
final readonly class AmendmentInput
{
    public function __construct(public ?int $hires, public ?int $salaryMin, public ?int $salaryMax, public string $reason) {}
}
