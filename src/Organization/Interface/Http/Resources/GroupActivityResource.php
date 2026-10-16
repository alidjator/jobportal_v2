<?php

namespace Src\Organization\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Activity\GroupActivityRow;
use Src\Shared\Application\Audit\AuditActorType;

/**
 * One entry of the group activity log. The actor is a member (`type` `user`, with their name), the platform (`admin`, no name: the client shows "Admin platform") or
 * the system. There is no IP address or user agent. `metadata` is the audit metadata of the action (for a dated change of a unit or position: the versions before and after).
 *
 * @property GroupActivityRow $resource
 */
final class GroupActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entry = $this->resource->entry;

        return [
            'id' => $entry->id,
            'occurred_at' => $entry->occurredAt->format(DateTimeInterface::ATOM),
            'actor' => ['type' => $entry->actorType->value, 'id' => $entry->actorType === AuditActorType::User ? $entry->actorId : null, 'name' => $this->resource->actorName],
            'action' => $entry->action,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'company_id' => $entry->companyId,
            'company_name' => $this->resource->companyName,
            'metadata' => (object) $entry->metadata,
        ];
    }
}
