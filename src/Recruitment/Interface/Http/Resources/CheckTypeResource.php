<?php

namespace Src\Recruitment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\CheckType;

/**
 * A kind of pre-employment check the company defined.
 *
 * @property CheckType $resource
 */
final class CheckTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'kind' => $this->resource->kind->value,
            'instructions' => $this->resource->instructions,
            'requires_consent' => $this->resource->requiresConsent,
            'is_active' => $this->resource->isActive,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
