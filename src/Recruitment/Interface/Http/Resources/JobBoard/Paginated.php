<?php

namespace Src\Recruitment\Interface\Http\Resources\JobBoard;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Src\Recruitment\Domain\JobBoard\Page;

/** Mengubah `Page` domain menjadi paginator Laravel agar bentuk JSON sama dengan aplikasi lama. */
final class Paginated
{
    public static function paginator(Page $page, bool $onEachSideZero = false, bool $withQueryString = false): LengthAwarePaginator
    {
        $paginator = new LengthAwarePaginator($page->items, $page->total, $page->perPage, $page->currentPage, [
            'path' => Paginator::resolveCurrentPath(),
        ]);

        if ($withQueryString) {
            $paginator->withQueryString();
        }

        if ($onEachSideZero) {
            $paginator->onEachSide(0);
        }

        return $paginator;
    }

    /**
     * Bentuk `JsonResource::collection($paginator)->response()->getData()`: {data, links, meta}.
     *
     * @return array<string, mixed>
     */
    public static function resourceShape(Page $page): array
    {
        return JsonResource::collection(self::paginator($page))->response()->getData(true);
    }
}
