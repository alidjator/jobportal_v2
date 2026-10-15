<?php

namespace Src\Company\Application\Activity;

use DateTimeImmutable;

/** The filters of the company activity log: one member, one group of actions, and a time range (both ends inclusive). */
final readonly class CompanyActivityFilter
{
    public function __construct(
        public ?int $userId = null,
        public ?string $group = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {}

    /** @return ?list<string> the actions of the group; null when no group was asked for */
    public function actions(): ?array
    {
        return $this->group === null ? null : CompanyActivityGroups::actions($this->group);
    }
}
