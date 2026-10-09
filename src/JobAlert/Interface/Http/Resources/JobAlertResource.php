<?php

namespace Src\JobAlert\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobAlert\Domain\JobAlert;

/** @property JobAlert $resource */
final class JobAlertResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'frequency' => $this->resource->frequency->value,
            'criteria' => $this->resource->criteria->toArray(),
            'email' => $this->resource->email,
            'in_app' => $this->resource->inApp,
            'paused' => $this->resource->paused,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
