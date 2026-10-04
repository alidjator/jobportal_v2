<?php

namespace Src\Platform\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Application\Audit\AuditLogRecord;

/** One audit entry. It holds IP addresses and user agents (personal data), which is why only admins with `audit_logs.view` can read it. @property AuditLogRecord $resource */
final class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'occurred_at' => $this->resource->occurredAt->format(DateTimeInterface::ATOM),
            'actor_type' => $this->resource->actorType->value,
            'actor_id' => $this->resource->actorId,
            'action' => $this->resource->action,
            'subject_type' => $this->resource->subjectType,
            'subject_id' => $this->resource->subjectId,
            'metadata' => (object) $this->resource->metadata,
            'ip_address' => $this->resource->ipAddress,
            'user_agent' => $this->resource->userAgent,
        ];
    }
}
