<?php

namespace Src\Notification\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Notification\Application\InApp\InAppNotification;

/**
 * One in-app notification: a stable `type` key and structured `data`; the client writes and translates the message.
 *
 * @property InAppNotification $resource
 */
final class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'data' => (object) $this->resource->data,
            'read_at' => $this->resource->readAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
