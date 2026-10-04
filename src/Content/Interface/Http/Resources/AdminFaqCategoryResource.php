<?php

namespace Src\Content\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Content\Domain\FaqCategory;

/** @property FaqCategory $resource */
final class AdminFaqCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name()->toArray(),
            'sort_order' => $this->resource->sortOrder(),
            'is_active' => $this->resource->isActive(),
        ];
    }
}
