<?php

namespace Src\Review\Interface\Http\Resources;

use DateTimeInterface;
use Src\Review\Domain\Report;

/** A report as the moderator sees it. */
final class ReportPayload
{
    /** @return array<string, mixed> */
    public static function of(Report $report): array
    {
        return [
            'id' => $report->id,
            'review_id' => $report->reviewId,
            'reporter' => ['kind' => $report->reporterKind->value, 'id' => $report->reporterId],
            'reason' => $report->reason->value,
            'note' => $report->note,
            'status' => $report->status->value,
            'resolved_at' => $report->resolvedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $report->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
