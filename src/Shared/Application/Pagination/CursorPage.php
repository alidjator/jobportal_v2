<?php

namespace Src\Shared\Application\Pagination;

/**
 * One slice of a cursor-paginated list. `nextCursor` is null on the last slice.
 *
 * @template TItem
 */
final readonly class CursorPage
{
    /** @param  list<TItem>  $items */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public int $perPage,
    ) {}
}
