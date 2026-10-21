<?php

namespace Src\Messaging\Application\Reports;

use Src\Messaging\Domain\Events\MessageReportDecided;
use Src\Messaging\Domain\MessageReport;
use Src\Messaging\Domain\MessageReportRepository;
use Src\Messaging\Domain\ReportStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * An admin holding `messages.moderate` decides an open report: `dismissed` (nothing wrong) or `actioned` (the admin acted; suspending the account or the company uses the
 * existing actions, this only records the decision). The reporter is told it was reviewed, never how. The audit entry holds the report id, the reason and the outcome, not the text.
 */
final readonly class DecideMessageReport
{
    public const int NOTE_MAX = 1000;

    public function __construct(
        private MessageReportRepository $reports,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     * @throws StateConflict `report-decided`
     * @throws BusinessRuleViolation `invalid-decision` when the note is too long, or missing for `actioned`
     */
    public function execute(Actor $actor, int $id, ReportStatus $outcome, ?string $note, ?string $ipAddress, ?string $userAgent): MessageReport
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can decide a conversation report.');
        }

        $note = $note === null || trim($note) === '' ? null : trim($note);

        if ($outcome === ReportStatus::Open || ($outcome === ReportStatus::Actioned && $note === null) || ($note !== null && mb_strlen($note) > self::NOTE_MAX)) {
            throw new BusinessRuleViolation(
                'Say what was done (at most :max characters).', 'invalid-decision', extensions: ['errors' => ['note' => ['Say what was done (at most :max characters).']]], parameters: ['max' => self::NOTE_MAX],
            );
        }

        $adminId = (int) $actor->id;
        $now = $this->clock->now();

        $report = $this->transactions->run(function () use ($id, $outcome, $note, $adminId, $now): MessageReport {
            $report = $this->reports->find($id, forUpdate: true) ?? throw EntityNotFound::of('MessageReport', $id);

            if ($report->status !== ReportStatus::Open) {
                throw new StateConflict('This report was already decided.', 'report-decided');
            }

            $this->reports->decide($id, $outcome, $adminId, $note, $now);

            return $report;
        });

        $this->audit->record(new AuditEntry(
            AuditActorType::Admin, $adminId, $outcome === ReportStatus::Dismissed ? AuditAction::MESSAGE_REPORT_DISMISSED : AuditAction::MESSAGE_REPORT_ACTIONED,
            'message_report', $id, ['reason' => $report->reason->value, 'conversation_id' => $report->conversationId], $ipAddress, $userAgent, $now,
        ));
        $this->events->dispatch([new MessageReportDecided($id, $report->conversationId, $report->reporterUserId, $outcome, $now)]);

        return $this->reports->find($id) ?? $report;
    }
}
