<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\NameChange\AdminNameChange;

/**
 * A name change request as an admin sees it: the company, who asked, who decided, and whether the proof is still there (it is deleted 90 days after the decision).
 *
 * @property AdminNameChange $resource
 */
final class AdminNameChangeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $change = $this->resource->request;
        $company = $this->resource->company;

        return [
            'id' => $change->id,
            'company' => $company === null
                ? ['id' => $change->companyId, 'name' => null, 'slug' => null, 'status' => null]
                : ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug, 'status' => $company->status],
            'requested_by_user_id' => $change->requestedByUserId,
            'current_name' => $change->currentName,
            'new_name' => $change->newName,
            'reason' => $change->reason,
            'document_name' => $change->documentName,
            'has_document' => $change->documentPath !== null,
            'status' => $change->status->value,
            'decided_by_admin_id' => $change->decidedByAdminId,
            'decision_note' => $change->decisionNote,
            'created_at' => $change->createdAt?->format(DateTimeInterface::ATOM),
            'decided_at' => $change->decidedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
