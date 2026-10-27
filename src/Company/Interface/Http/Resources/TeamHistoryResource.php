<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Team\TeamHistoryRow;
use Src\Shared\Application\Audit\AuditActorType;

/**
 * One change of the team. A platform admin shows as `actor_type` `admin` without a name. It has no IP address or user agent.
 *
 * @property TeamHistoryRow $resource
 */
final class TeamHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'id' => $row->entry->id,
            'occurred_at' => $row->entry->occurredAt->format(DateTimeInterface::ATOM),
            'action' => $row->entry->action,
            'actor' => [
                'type' => $row->entry->actorType === AuditActorType::Admin ? 'platform_admin' : $row->entry->actorType->value,
                'user_id' => $row->entry->actorType === AuditActorType::User ? $row->entry->actorId : null,
                'name' => $row->actorName,
            ],
            'member' => ['user_id' => $row->memberUserId, 'name' => $row->memberName, 'email' => $row->memberEmail],
            'changes' => (object) $row->entry->metadata,
        ];
    }
}
