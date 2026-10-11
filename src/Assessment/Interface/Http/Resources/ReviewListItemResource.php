<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Review\ReviewListItem;

/** One line of the psychologist's review queue. @property ReviewListItem $resource */
final class ReviewListItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $summary = $this->resource->summary;

        return [
            'id' => $summary->reportId,
            'invitation_id' => $summary->invitationId,
            'candidate_name' => $this->resource->candidateName,
            'test_title' => $summary->testTitle,
            'status' => $summary->status->value,
            'report_number' => $summary->number,
            'submitted_at' => $summary->submittedAt->format(DateTimeInterface::ATOM),
            'signed_at' => $summary->signedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
