<?php

namespace Src\Dashboard\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Dashboard\Application\DashboardView;

/** @property DashboardView $resource */
final class DashboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['generated_at' => $this->resource->generatedAt, 'cards' => (object) $this->resource->cards];
    }
}
