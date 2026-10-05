<?php

namespace Src\Company\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Domain\DocumentType;

/** The admin view of a document type: both languages, the flags and the order. @property DocumentType $resource */
final class DocumentTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name->toArray(),
            'is_required' => $this->resource->isRequired,
            'is_active' => $this->resource->isActive,
            'sort_order' => $this->resource->sortOrder,
        ];
    }
}
