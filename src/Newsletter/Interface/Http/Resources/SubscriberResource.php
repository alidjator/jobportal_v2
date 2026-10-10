<?php

namespace Src\Newsletter\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Newsletter\Domain\Subscriber;

/** @property Subscriber $resource */
final class SubscriberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'email' => $this->resource->email,
            'status' => $this->resource->status->value,
            'locale' => $this->resource->locale,
            'confirmed_at' => $this->resource->confirmedAt?->format(DateTimeInterface::ATOM),
            'unsubscribed_at' => $this->resource->unsubscribedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
