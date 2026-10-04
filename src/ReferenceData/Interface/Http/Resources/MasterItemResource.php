<?php

namespace Src\ReferenceData\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\ReferenceData\Application\Masters\MasterItem;
use Src\Shared\Interface\Http\RequestLanguage;

/** The public view of a master item: the name in the request language, plus the master's extra columns. @property MasterItem $resource */
final class MasterItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name->resolve(RequestLanguage::of($request)),
            ...$this->resource->extras,
        ];
    }
}
