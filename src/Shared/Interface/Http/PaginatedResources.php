<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Shared\Application\Pagination\Page;

/**
 * Turns a page of items into the standard Laravel paginator, so a resource collection carries `links` and `meta` (with the total). A controller returns
 * `XResource::collection(PaginatedResources::paginator(...))`, which is also what lets the API documentation see the resource.
 */
final class PaginatedResources
{
    /** @param  Page<mixed>  $page */
    public static function paginator(Page $page, Request $request): LengthAwarePaginator
    {
        return (new LengthAwarePaginator(
            $page->items,
            $page->total,
            $page->request->perPage,
            $page->request->page,
            ['path' => $request->url()],
        ))->appends($request->query());
    }
}
