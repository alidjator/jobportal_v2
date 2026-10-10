<?php

namespace Src\TalentPool\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\TalentPool\Application\CategoryView;

/**
 * A category of the company with the number of candidates in it.
 *
 * @property CategoryView $resource
 */
final class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name, 'entries' => $this->resource->entries];
    }
}
