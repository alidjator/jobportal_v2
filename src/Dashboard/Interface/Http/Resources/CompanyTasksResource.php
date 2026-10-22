<?php

namespace Src\Dashboard\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What waits for a company member: one item per task the member may use, with the query (`filter`) that opens it.
 *
 * @property array{generated_at: string, items: list<array{key: string, count: int, filter: string}>} $resource
 */
final class CompanyTasksResource extends JsonResource
{
    /** @return array{generated_at: string, items: list<array{key: string, count: int, filter: string}>} */
    public function toArray(Request $request): array
    {
        return [
            'generated_at' => $this->resource['generated_at'],
            /** @var list<array{key: string, count: int, filter: string}> */
            'items' => $this->resource['items'],
        ];
    }
}
