<?php

namespace Src\Content\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Content\Domain\LegalDocument;
use Src\Shared\Interface\Http\RequestLanguage;

/**
 * The public view of a legal document: title and body in the request language (Markdown body, rendered and sanitised by the client).
 *
 * @property LegalDocument $resource
 */
final class LegalDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $language = RequestLanguage::of($request);

        return [
            'type' => $this->resource->type->value,
            'version' => $this->resource->version,
            'title' => $this->resource->title()->resolve($language),
            'body' => $this->resource->body()->resolve($language),
            'published_at' => $this->resource->publishedAt()?->format(DateTimeInterface::ATOM),
        ];
    }
}
