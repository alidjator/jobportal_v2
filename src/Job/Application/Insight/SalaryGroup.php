<?php

namespace Src\Job\Application\Insight;

/** The jobs one figure is computed over: a role (or, for the last widening step, a category) and optionally a place and an experience level. */
final readonly class SalaryGroup
{
    public function __construct(
        public ?int $roleId,
        public ?int $categoryId,
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $experienceLevelId,
    ) {}

    /** A stable text for the cache key. */
    public function key(): string
    {
        return implode('|', [$this->roleId ?? '-', $this->categoryId ?? '-', $this->provinceId ?? '-', $this->cityId ?? '-', $this->experienceLevelId ?? '-']);
    }
}
