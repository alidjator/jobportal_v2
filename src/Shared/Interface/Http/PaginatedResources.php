<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Shared\Application\Pagination\Page;

/** Wraps a page of items in a resource collection that carries the standard Laravel `links` and `meta` (with the total). */
final class PaginatedResources
{
    /**
     * @param  Page<mixed>  $page
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function of(Page $page, string $resourceClass, Request $request): AnonymousResourceCollection
    {
        $paginator = new LengthAwarePaginator(
            $page->items,
            $page->total,
            $page->request->perPage,
            $page->request->page,
            ['path' => $request->url()],
        );

        return $resourceClass::collection($paginator->appends($request->query()));
    }
}
