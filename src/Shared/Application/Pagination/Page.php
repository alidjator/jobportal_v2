<?php

namespace Src\Shared\Application\Pagination;

/**
 * One page of a list with the total, as returned by a paginated query.
 *
 * @template TItem
 */
final readonly class Page
{
    /** @param  list<TItem>  $items */
    public function __construct(
        public array $items,
        public int $total,
        public PageRequest $request,
    ) {}
}
