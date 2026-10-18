<?php

namespace Src\Messaging\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Messaging\Application\Reports\MessageReportDetail;

/** A report as the admin reads it: the snapshot of the reported messages (never the live thread; null once cleared), the note and the decision. @property MessageReportDetail $resource */
final class AdminMessageReportDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $report = $this->resource->report;

        return [
            'id' => $report->id,
            'conversation_id' => $report->conversationId,
            'message_id' => $report->messageId,
            'company' => ['id' => $this->resource->companyId, 'name' => $this->resource->companyName],
            'job_seeker' => ['id' => $this->resource->jobSeekerUserId, 'name' => $this->resource->jobSeekerName],
            'reporter_side' => $report->reporterSide->value,
            'reporter_user_id' => $report->reporterUserId,
            'reason' => $report->reason->value,
            'note' => $report->note,
            'snapshot' => $report->snapshot,
            'status' => $report->status->value,
            'decided_by_admin_id' => $report->decidedByAdminId,
            'decided_at' => $report->decidedAt?->format(DateTimeInterface::ATOM),
            'decision_note' => $report->decisionNote,
            'created_at' => $report->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
