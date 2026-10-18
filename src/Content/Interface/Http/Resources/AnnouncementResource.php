<?php

namespace Src\Content\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Content\Domain\Announcement;

/** A live announcement as the banner needs it. @property Announcement $resource */
final class AnnouncementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'link_url' => $this->resource->linkUrl,
            'level' => $this->resource->level->value,
            'starts_at' => $this->resource->startsAt->format(DateTimeInterface::ATOM),
            'ends_at' => $this->resource->endsAt->format(DateTimeInterface::ATOM),
        ];
    }
}
