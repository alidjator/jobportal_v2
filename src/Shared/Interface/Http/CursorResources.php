<?php

namespace Src\Shared\Interface\Http;

use Illuminate\Http\Request;
use Src\Shared\Application\Pagination\CursorPage;

/**
 * The envelope of a cursor slice: `links.next` (null on the last slice) and `meta.per_page` and `meta.next_cursor`. There is no total. A controller returns
 * `XResource::collection($page->items)->additional(CursorResources::envelope($page, $request))`.
 */
final class CursorResources
{
    /**
     * @param  CursorPage<mixed>  $page
     * @return array{links: array{next: string|null}, meta: array{per_page: int, next_cursor: string|null}}
     */
    public static function envelope(CursorPage $page, Request $request): array
    {
        $next = $page->nextCursor === null
            ? null
            : $request->url().'?'.http_build_query([...$request->query(), 'cursor' => $page->nextCursor]);

        return [
            'links' => ['next' => $next],
            'meta' => ['per_page' => $page->perPage, 'next_cursor' => $page->nextCursor],
        ];
    }
}
