<?php

namespace Src\Content\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Content\Domain\Faq;

/** @property Faq $resource */
final class AdminFaqResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'category_id' => $this->resource->categoryId(),
            'question' => $this->resource->question()->toArray(),
            'answer' => $this->resource->answer()->toArray(),
            'sort_order' => $this->resource->sortOrder(),
            'is_active' => $this->resource->isActive(),
        ];
    }
}
