<?php

namespace Src\Shared\Application\Pagination;

use InvalidArgumentException;

/** Which page of a list to read: `page` from 1, `per_page` from 1 to 100 (default 20). Out-of-range values are refused, never silently capped. */
final readonly class PageRequest
{
    public const int DEFAULT_PER_PAGE = 20;

    public const int MAX_PER_PAGE = 100;

    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        if ($page < 1 || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException('The page must be at least 1 and the page size between 1 and '.self::MAX_PER_PAGE.'.');
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
