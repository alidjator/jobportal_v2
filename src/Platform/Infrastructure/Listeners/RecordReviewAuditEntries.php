<?php

namespace Src\Platform\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Platform\Application\Audit\AuditAction;
use Src\Platform\Application\Audit\AuditActorType;
use Src\Platform\Application\Audit\AuditEntry;
use Src\Platform\Application\Audit\RecordAuditEntry;
use Src\Review\Domain\Events\ReviewModerated;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for the Review context: every decision of a moderator on a review, a reply or a report. The entries hold ids and the reason code, never
 * the text. Runs on the `default` queue, retried 3 times with a growing delay.
 */
final class RecordReviewAuditEntries implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly RecordAuditEntry $record) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onReviewModerated(ReviewModerated $event): void
    {
        [$action, $subject] = match ($event->action) {
            ReviewModerated::REVIEW_APPROVED => [AuditAction::REVIEW_APPROVED, 'review'],
            ReviewModerated::REVIEW_REJECTED => [AuditAction::REVIEW_REJECTED, 'review'],
            ReviewModerated::REVIEW_REMOVED => [AuditAction::REVIEW_REMOVED, 'review'],
            ReviewModerated::REPLY_APPROVED => [AuditAction::REVIEW_REPLY_APPROVED, 'review'],
            ReviewModerated::REPLY_REJECTED => [AuditAction::REVIEW_REPLY_REJECTED, 'review'],
            ReviewModerated::REPLY_REMOVED => [AuditAction::REVIEW_REPLY_REMOVED, 'review'],
            ReviewModerated::REPORT_DISMISSED => [AuditAction::REVIEW_REPORT_DISMISSED, 'review_report'],
            default => throw new \LogicException("Unknown review moderation {$event->action}."),
        };

        $metadata = ['company_id' => $event->companyId];

        if ($event->reason !== null) {
            $metadata['reason'] = $event->reason;
        }

        $this->record->execute(new AuditEntry(
            AuditActorType::Admin, $event->adminId, $action, $subject, $event->subjectId, $metadata, $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
