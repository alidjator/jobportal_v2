<?php

namespace Src\Organization\Application\Activity;

use DateTimeImmutable;

/** The filters of the group activity log: one member, one tenant (company), one action, and a time range (both ends inclusive). */
final readonly class GroupActivityFilter
{
    public function __construct(
        public ?int $userId = null,
        public ?int $companyId = null,
        public ?string $action = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {}
}
