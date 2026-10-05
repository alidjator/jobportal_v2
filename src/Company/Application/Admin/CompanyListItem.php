<?php

namespace Src\Company\Application\Admin;

use DateTimeImmutable;
use Src\Company\Domain\CompanyStatus;

/** One row of the admin's company list. */
final readonly class CompanyListItem
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public CompanyStatus $status,
        public bool $verified,
        public int $membersCount,
        public DateTimeImmutable $createdAt,
    ) {}
}
