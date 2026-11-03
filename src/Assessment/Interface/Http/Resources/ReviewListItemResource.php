<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Review\ReviewListItem;
use Src\Assessment\Domain\ReportStatus;

/**
 * One line of the psychologist's review queue.
 *
 * @property ReviewListItem $resource
 */
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
            'job_title' => $this->resource->jobTitle,
            'company_name' => $this->resource->companyName,
            /** The holder of the live claim. */
            'claimed_by' => $this->resource->claimedBy,
            'status' => $summary->status->value,
            'report_number' => $summary->number,
            'submitted_at' => $summary->submittedAt->format(DateTimeInterface::ATOM),
            'signed_at' => $summary->signedAt?->format(DateTimeInterface::ATOM),
            'auto_issued' => $summary->autoIssued,
            'reviewed' => ! $summary->autoIssued || $summary->reviewedAt !== null,
            /** The day the review is due (`Y-m-d`, Jakarta working days); null once signed and for an automatic report. */
            'review_due_on' => $summary->status === ReportStatus::PendingReview ? $summary->reviewDueOn?->format('Y-m-d') : null,
            /** Whether the last message about the report is a company's question nobody answered. */
            'open_question' => $summary->hasOpenQuestion,
            /** Whether the review is past its due day; overdue reports come first in the queue. */
            'overdue' => $this->resource->overdue,
        ];
    }
}
