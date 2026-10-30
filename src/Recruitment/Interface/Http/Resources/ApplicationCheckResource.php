<?php

namespace Src\Recruitment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\Checks\CheckView;

/**
 * A check on an application as the team reads it. The status, the schedule and the vendor show to everyone who sees the application; the outcome, the note and the file to a member with `checks.manage`
 * (`outcome_visible`), the others get null there.
 *
 * @property CheckView $resource
 */
final class ApplicationCheckResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $check = $this->resource->check;
        $type = $this->resource->type;
        $visible = $this->resource->outcomeVisible;

        return [
            'id' => $check->id,
            'application_id' => $check->applicationId,
            'type' => ['id' => $type->id, 'name' => $type->name, 'kind' => $type->kind->value],
            'status' => $check->status->value,
            'consent_required' => $check->consentRequired,
            'consent_pending' => $check->awaitsConsent(),
            'consented_at' => $check->consentedAt?->format(DateTimeInterface::ATOM),
            'consent_refused_at' => $check->consentRefusedAt?->format(DateTimeInterface::ATOM),
            'refusal_reason' => $visible ? $check->refusalReason : null,
            'scheduled_at' => $check->scheduledAt?->format(DateTimeInterface::ATOM),
            'place' => $check->place,
            'vendor' => $check->vendor,
            'note_to_candidate' => $check->noteToCandidate,
            'outcome_visible' => $visible,
            'outcome' => $visible ? $check->outcome?->value : null,
            'result_note' => $visible ? $check->resultNote : null,
            'has_result_file' => $visible && $check->resultFilePath !== null,
            'result_file_name' => $visible ? $check->resultFileName : null,
            'result_recorded_at' => $check->resultRecordedAt?->format(DateTimeInterface::ATOM),
            'requested_by' => $check->requestedBy,
            'created_at' => $check->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
