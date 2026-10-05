<?php

namespace Src\Company\Application\Documents;

/** The documents of one company for a platform admin: a slot per active type (and per inactive type that has a document), and whether the company is verified. */
final readonly class AdminDocumentsOverview
{
    /** @param  list<AdminDocumentSlot>  $slots */
    public function __construct(
        public array $slots,
        public bool $verified,
    ) {}
}
