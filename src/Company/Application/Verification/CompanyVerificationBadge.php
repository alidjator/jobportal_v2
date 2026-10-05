<?php

namespace Src\Company\Application\Verification;

/**
 * Keeps `verified_at` of companies in line with their documents. A company is verified when at least one active required document type
 * exists and the company has an `approved` document for every one of them; otherwise it is not. A company that stays verified keeps its
 * original date. Callers run it inside the transaction that changed the documents or the types.
 */
interface CompanyVerificationBadge
{
    /** Recalculates every company (used when the list of types or their flags change). */
    public function recalculateAll(): void;

    /** Recalculates one company (used when its documents change). */
    public function recalculateFor(int $companyId): void;
}
