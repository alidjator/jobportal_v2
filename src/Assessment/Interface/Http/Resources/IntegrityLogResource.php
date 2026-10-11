<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Results\IntegrityLog;

/** The integrity signals of one attempt with their times and the tier they add up to. @property IntegrityLog $resource */
final class IntegrityLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'tier' => $this->resource->tier->value,
            'count' => $this->resource->count,
            'events' => array_map(static fn (array $event): array => [
                'type' => $event['type']->value,
                'occurred_at' => $event['occurred_at']->format(DateTimeInterface::ATOM),
            ], $this->resource->events),
        ];
    }
}
