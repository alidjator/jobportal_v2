<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Team\ActivityRow;

/**
 * One action of a member. It holds the IP address and user agent, which only a company admin reads (the privacy notice says so).
 *
 * @property ActivityRow $resource
 */
final class MemberActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entry = $this->resource->entry;

        return [
            'id' => $entry->id,
            'occurred_at' => $entry->occurredAt->format(DateTimeInterface::ATOM),
            'action' => $entry->action,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'subject_title' => $this->resource->subjectTitle,
            'ip_address' => $entry->ipAddress,
            'user_agent' => $entry->userAgent,
        ];
    }
}
