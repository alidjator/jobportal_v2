<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Pagination\CursorPage;

/** Wraps a cursor slice in a resource collection: `data`, `links.next` (null on the last slice) and `meta.per_page` and `meta.next_cursor`. There is no total. */
final class CursorResources
{
    /**
     * @param  CursorPage<mixed>  $page
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function of(CursorPage $page, string $resourceClass, Request $request): AnonymousResourceCollection
    {
        $next = $page->nextCursor === null
            ? null
            : $request->url().'?'.http_build_query([...$request->query(), 'cursor' => $page->nextCursor]);

        return $resourceClass::collection($page->items)->additional([
            'links' => ['next' => $next],
            'meta' => ['per_page' => $page->perPage, 'next_cursor' => $page->nextCursor],
        ]);
    }
}
