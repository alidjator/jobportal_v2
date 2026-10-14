<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Review\ReviewView;
use Src\Assessment\Domain\SpeedColumnResult;

/** One report as the psychologist reviews it. @property ReviewView $resource */
final class ReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $report = $this->resource->report;

        return [
            'id' => $report->id,
            'invitation_id' => $this->resource->invitationId,
            'status' => $report->status->value,
            'candidate_name' => $this->resource->candidateName,
            'test_title' => $this->resource->testTitle,
            'submitted_at' => $this->resource->submittedAt?->format(DateTimeInterface::ATOM),
            'integrity_tier' => $this->resource->integrityTier->value,
            'body' => $report->body,
            'explanation' => $report->explanation,
            'review_notes' => $report->reviewNotes,
            'edited_at' => $report->editedAt?->format(DateTimeInterface::ATOM),
            'scales' => $this->resource->scales,
            'speed_columns' => array_map(static fn (SpeedColumnResult $column): array => [
                'section_id' => $column->sectionId,
                'column_no' => $column->columnNo,
                'attempted' => $column->attempted,
                'correct' => $column->correct,
            ], $this->resource->columns),
            'drawings' => $this->resource->drawings,
            'signing_problems' => $this->resource->signingProblems,
            'report_number' => $report->number,
            'signed_at' => $report->signedAt?->format(DateTimeInterface::ATOM),
            'signer_name' => $report->signerName,
            'auto_issued' => $report->autoIssued,
            'reviewed' => ! $report->autoIssued || $report->reviewedAt !== null,
            'reviewed_at' => $report->reviewedAt?->format(DateTimeInterface::ATOM),
            'revision' => $report->revision,
        ];
    }
}
