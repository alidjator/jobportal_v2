<?php

namespace Src\Company\Domain;

/** Why an admin rejected a document: a fixed list that clients translate. `other` always comes with a note. */
enum RejectionReason: string
{
    case Unreadable = 'unreadable';
    case Expired = 'expired';
    case WrongDocument = 'wrong_document';
    case CompanyMismatch = 'company_mismatch';
    case Incomplete = 'incomplete';
    case Other = 'other';

    public function requiresNote(): bool
    {
        return $this === self::Other;
    }
}
