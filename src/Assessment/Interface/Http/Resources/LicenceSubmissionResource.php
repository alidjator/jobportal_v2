<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\LicenceSubmission;

/**
 * A licence submission as the psychologist sees it: what they sent and what was decided. The document has no URL; the admin reads it through its own endpoint.
 *
 * @property LicenceSubmission $resource
 */
final class LicenceSubmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }

    /** @return array<string, mixed> */
    public static function fields(LicenceSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'kind' => $submission->kind->value,
            'number' => $submission->number,
            'valid_until' => $submission->validUntil->format('Y-m-d'),
            'document_name' => $submission->documentName,
            'status' => $submission->status()->value,
            'decision_note' => $submission->decisionNote(),
            'decided_at' => $submission->decidedAt()?->format(DateTimeInterface::ATOM),
            'submitted_at' => $submission->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
