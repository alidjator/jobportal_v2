<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Activity\CompanyActivityRow;
use Src\Shared\Application\Audit\AuditActorType;

/**
 * One entry of the company activity log. The actor is a member (`type` `user`, with their name), the platform (`admin`, no name: the client shows "Admin
 * platform") or the system. The IP address and user agent are those of a member's action; a platform admin's stay with the platform.
 *
 * @property CompanyActivityRow $resource
 */
final class CompanyActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entry = $this->resource->entry;
        $isMember = $entry->actorType === AuditActorType::User;

        return [
            'id' => $entry->id,
            'occurred_at' => $entry->occurredAt->format(DateTimeInterface::ATOM),
            'actor' => ['type' => $entry->actorType->value, 'id' => $isMember ? $entry->actorId : null, 'name' => $this->resource->actorName],
            'action' => $entry->action,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'subject_title' => $this->resource->subjectTitle,
            'ip_address' => $isMember ? $entry->ipAddress : null,
            'user_agent' => $isMember ? $entry->userAgent : null,
        ];
    }
}
