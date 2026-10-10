<?php

namespace Src\Company\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Documents\CompanyDocumentSlot;
use Src\Company\Application\Documents\CompanyDocumentsOverview;
use Src\Company\Domain\DocumentStatus;

/**
 * The documents page of a company. The rejection reason and note appear only while a document is rejected. The stored file path is never shown.
 *
 * @property CompanyDocumentsOverview $resource
 */
final class CompanyDocumentsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'verified' => $this->resource->verified,
            /** @var list<array{type: array{id: int, slug: string, name: string, is_required: bool}, document: array{status: string, original_name: string, mime_type: string, size_bytes: int, submitted_at: string, reviewed_at: string|null, rejection_reason?: string|null, rejection_note?: string|null}|null}> */
            'documents' => array_map(fn (CompanyDocumentSlot $slot): array => $this->slot($slot), $this->resource->slots),
        ];
    }

    /** @return array<string, mixed> */
    private function slot(CompanyDocumentSlot $slot): array
    {
        $document = $slot->document;

        return [
            'type' => [
                'id' => $slot->type->id,
                'slug' => $slot->type->slug,
                'name' => $slot->typeName,
                'is_required' => $slot->type->isRequired,
            ],
            'document' => $document === null ? null : [
                'status' => $document->status->value,
                'original_name' => $document->originalName,
                'mime_type' => $document->mimeType,
                'size_bytes' => $document->sizeBytes,
                'submitted_at' => $document->submittedAt->format(DATE_ATOM),
                'reviewed_at' => $document->reviewedAt?->format(DATE_ATOM),
                ...($document->status === DocumentStatus::Rejected
                    ? ['rejection_reason' => $document->rejectionReason, 'rejection_note' => $document->rejectionNote]
                    : []),
            ],
        ];
    }
}
