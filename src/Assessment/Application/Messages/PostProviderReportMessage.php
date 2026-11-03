<?php

namespace Src\Assessment\Application\Messages;

use Src\Assessment\Application\Delivery\InvitationViews;
use Src\Assessment\Application\Review\ReviewAccess;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\ReportMessage;
use Src\Assessment\Domain\ReportMessageAuthor;
use Src\Assessment\Domain\ReportMessageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A psychologist of the institution (with a valid licence) answers about a signed report, in the same 30 days. The same plain-text and link rule as the company's side applies.
 */
final readonly class PostProviderReportMessage
{
    public function __construct(
        private ReviewAccess $access,
        private AttemptRepository $attempts,
        private InvitationRepository $invitations,
        private InvitationViews $views,
        private ReportMessageRepository $messages,
        private ReportThreads $threads,
        private ReportMessageNotices $notices,
        private CandidateMessageRules $rules,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended`, `provider-suspended` or `cannot-sign-reports`
     * @throws EntityNotFound
     * @throws StateConflict `report-questions-closed`
     * @throws BusinessRuleViolation `message-link-not-allowed`
     */
    public function execute(Actor $actor, int $reportId, string $body): ReportThread
    {
        return $this->transactions->run(function () use ($actor, $reportId, $body): ReportThread {
            $psychologist = $this->access->psychologist($actor);
            $report = $this->access->report($psychologist, $reportId, forUpdate: true);

            if (! $psychologist->canSignReports($this->clock->now())) {
                throw new AccessDenied('Your STR or SILP is not valid today, so you cannot answer about reports.', 'cannot-sign-reports');
            }

            $report->assertAcceptsQuestions($this->clock->now());

            $attempt = $this->attempts->find($report->attemptId) ?? throw EntityNotFound::of('Report', $reportId);
            $invitation = $this->invitations->find($attempt->invitationId) ?? throw EntityNotFound::of('Report', $reportId);

            $text = trim($body);
            $this->rules->assertAllowed($invitation->companyId, $text, 'body');

            $this->messages->add(new ReportMessage(null, (int) $report->id, ReportMessageAuthor::Psychologist, (int) $actor->id, $text));
            $this->notices->answered($report, $invitation, $this->views->one($invitation)->testTitle);

            return $this->threads->build($report, ReportMessageAuthor::Psychologist, (int) $actor->id);
        });
    }
}
