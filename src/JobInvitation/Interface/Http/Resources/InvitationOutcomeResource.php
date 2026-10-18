<?php

namespace Src\JobInvitation\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobInvitation\Application\InvitationOutcome;

/**
 * What happened to one entry of a bulk invitation: `invited` (with `invitation_id`) or `skipped` (with `code`: `entry-unavailable`, `already-invited`, `already-applied` or
 * `invitation-limit`).
 *
 * @property InvitationOutcome $resource
 */
final class InvitationOutcomeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'entry_id' => $this->resource->entryId,
            'status' => $this->resource->wasInvited() ? 'invited' : 'skipped',
            'code' => $this->resource->skipCode,
            'invitation_id' => $this->resource->invitationId,
        ];
    }
}
