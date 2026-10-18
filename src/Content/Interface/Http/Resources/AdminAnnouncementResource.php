<?php

namespace Src\Content\Interface\Http\Resources;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Content\Domain\Announcement;

/** An announcement for the admin pages, with where it stands now (`scheduled`, `live` or `ended`). @property Announcement $resource */
final class AdminAnnouncementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'link_url' => $this->resource->linkUrl,
            'audience' => $this->resource->audience->value,
            'level' => $this->resource->level->value,
            'starts_at' => $this->resource->startsAt->format(DateTimeInterface::ATOM),
            'ends_at' => $this->resource->endsAt->format(DateTimeInterface::ATOM),
            'status' => $this->resource->status(new DateTimeImmutable),
            'created_by_admin_id' => $this->resource->createdByAdminId,
        ];
    }
}
