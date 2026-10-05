<?php

namespace Src\Company\Application\Documents;

/** The documents page of a company: a slot per active type, and whether the company currently holds the verification badge. */
final readonly class CompanyDocumentsOverview
{
    /** @param  list<CompanyDocumentSlot>  $slots */
    public function __construct(
        public array $slots,
        public bool $verified,
    ) {}
}
