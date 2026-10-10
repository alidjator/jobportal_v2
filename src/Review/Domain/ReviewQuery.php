<?php

namespace Src\Review\Domain;

/** The filters of the admin list of reviews; null means no filter. `reported` keeps the reviews with at least one open report. */
final readonly class ReviewQuery
{
    public function __construct(
        public ?ReviewStatus $status = null,
        public ?int $companyId = null,
        public bool $reported = false,
    ) {}
}
