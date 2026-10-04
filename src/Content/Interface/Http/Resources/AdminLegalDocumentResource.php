<?php

namespace Src\Content\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Content\Domain\LegalDocument;

/** The admin view of a legal document: every language, status and author. @property LegalDocument $resource */
final class AdminLegalDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'version' => $this->resource->version,
            'status' => $this->resource->status()->value,
            'title' => $this->resource->title()->toArray(),
            'body' => $this->resource->body()->toArray(),
            'published_at' => $this->resource->publishedAt()?->format(DateTimeInterface::ATOM),
            'created_by_admin_id' => $this->resource->createdByAdminId,
        ];
    }
}
