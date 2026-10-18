<?php

namespace Src\Shared\Application\Company;

/** Reads the letterhead of a company for the documents another context writes in its name (an offer letter). */
interface CompanyLetterheads
{
    public function for(int $companyId): ?CompanyLetterhead;
}
