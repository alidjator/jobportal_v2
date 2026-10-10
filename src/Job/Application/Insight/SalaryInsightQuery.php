<?php

namespace Src\Job\Application\Insight;

/** What a visitor asked: a role, and optionally a place, an experience level and (for the last widening step) a category. */
final readonly class SalaryInsightQuery
{
    public function __construct(
        public int $roleId,
        public ?int $provinceId = null,
        public ?int $cityId = null,
        public ?int $experienceLevelId = null,
        public ?int $categoryId = null,
    ) {}
}
