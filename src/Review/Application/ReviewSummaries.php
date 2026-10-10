<?php

namespace Src\Review\Application;

use Src\Review\Domain\ReviewSummary;

/** The summary of a company's published reviews. The cached implementation keeps it for a short time; every change to a published review drops it. */
interface ReviewSummaries
{
    public function forCompany(int $companyId): ReviewSummary;

    public function forget(int $companyId): void;
}
