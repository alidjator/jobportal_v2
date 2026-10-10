<?php

namespace Src\Job\Application\Insight;

use DateTimeImmutable;

/**
 * The answer. `$figures` is null when no group had enough jobs. `$scope` names the group that was used (`level` is `role` or `category`); `$widened` tells that
 * it is wider than what was asked. Each named part is `['id' => int, 'name' => string|null]` or null.
 */
final readonly class SalaryInsight
{
    /**
     * @param  array{id: int, name: string|null}  $role
     * @param  array{id: int, name: string|null}|null  $category
     * @param  array{id: int, name: string|null}|null  $province
     * @param  array{id: int, name: string|null}|null  $city
     * @param  array{id: int, name: string|null}|null  $experienceLevel
     */
    public function __construct(
        public int $windowMonths,
        public array $role,
        public ?SalaryFigures $figures,
        public string $level,
        public ?array $category,
        public ?array $province,
        public ?array $city,
        public ?array $experienceLevel,
        public bool $widened,
        public DateTimeImmutable $updatedAt,
    ) {}
}
