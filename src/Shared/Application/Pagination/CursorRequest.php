<?php

namespace Src\Shared\Application\Pagination;

use InvalidArgumentException;

/**
 * Which slice of a growing feed to read: an opaque `cursor` returned by the previous slice (null for the first one) and the same
 * `per_page` limits as offset pagination (1 to 100, default 20). Cursor lists have no total and no page numbers.
 */
final readonly class CursorRequest
{
    public function __construct(
        public ?string $cursor = null,
        public int $perPage = PageRequest::DEFAULT_PER_PAGE,
    ) {
        if ($perPage < 1 || $perPage > PageRequest::MAX_PER_PAGE) {
            throw new InvalidArgumentException('The page size must be between 1 and '.PageRequest::MAX_PER_PAGE.'.');
        }
    }
}
