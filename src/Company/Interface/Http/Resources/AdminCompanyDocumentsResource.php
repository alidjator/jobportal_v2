<?php

namespace Src\Company\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Documents\AdminDocumentSlot;
use Src\Company\Application\Documents\AdminDocumentsOverview;

/**
 * The documents of a company as a platform admin sees them: both language names, the review details, and the exact `submitted_at` to send back
 * when approving or rejecting. The stored file path is never shown.
 *
 * @property AdminDocumentsOverview $resource
 */
final class AdminCompanyDocumentsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'verified' => $this->resource->verified,
            /** @var list<array{type: array{id: int, slug: string, name: array<string, string>, is_required: bool, is_active: bool}, document: array{status: string, original_name: string, mime_type: string, size_bytes: int, submitted_at: string, reviewed_at: string|null, reviewed_by_admin_id: int|null, rejection_reason: string|null, rejection_note: string|null}|null}> */
            'documents' => array_map(fn (AdminDocumentSlot $slot): array => $this->slot($slot), $this->resource->slots),
        ];
    }

    /** @return array<string, mixed> */
    private function slot(AdminDocumentSlot $slot): array
    {
        $document = $slot->document;

        return [
            'type' => [
                'id' => $slot->type->id,
                'slug' => $slot->type->slug,
                'name' => $slot->type->name->toArray(),
                'is_required' => $slot->type->isRequired,
                'is_active' => $slot->type->isActive,
            ],
            'document' => $document === null ? null : [
                'status' => $document->status->value,
                'original_name' => $document->originalName,
                'mime_type' => $document->mimeType,
                'size_bytes' => $document->sizeBytes,
                'submitted_at' => $document->submittedAt->format(DATE_ATOM),
                'reviewed_at' => $document->reviewedAt?->format(DATE_ATOM),
                'reviewed_by_admin_id' => $document->reviewedByAdminId,
                'rejection_reason' => $document->rejectionReason,
                'rejection_note' => $document->rejectionNote,
            ],
        ];
    }
}
