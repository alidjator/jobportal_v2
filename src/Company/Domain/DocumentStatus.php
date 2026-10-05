<?php

namespace Src\Company\Domain;

/** Where a company's document for one type stands. The values match the check constraint of `company_documents.status`. */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
