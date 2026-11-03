<?php

namespace Src\Assessment\Application\Messages;

use Src\Assessment\Application\Delivery\InvitationViews;
use Src\Assessment\Domain\ReportMessage;
use Src\Assessment\Domain\ReportMessageAuthor;
use Src\Assessment\Domain\ReportMessageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company member asks about a signed report. Needs `assessments.send` (a company admin has it) on top of the right to read the result; only a signed report takes questions, for 30 days after its signing.
 * The text is plain and carries no link or address outside the company's own domain.
 */
final readonly class PostCompanyReportMessage
{
    public function __construct(
        private CompanyReportAccess $access,
        private ReportMessageRepository $messages,
        private ReportThreads $threads,
        private ReportMessageNotices $notices,
        private CandidateMessageRules $rules,
        private InvitationViews $views,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `report-questions-closed`
     * @throws BusinessRuleViolation `message-link-not-allowed`
     */
    public function execute(Actor $actor, int $invitationId, string $body): ReportThread
    {
        $actor->membership?->require(CompanyPermission::AssessmentsSend);

        return $this->transactions->run(function () use ($actor, $invitationId, $body): ReportThread {
            [$invitation, $report] = $this->access->of($actor, $invitationId, forUpdate: true);
            $report->assertAcceptsQuestions($this->clock->now());

            $text = trim($body);
            $this->rules->assertAllowed($invitation->companyId, $text, 'body');

            $this->messages->add(new ReportMessage(null, (int) $report->id, ReportMessageAuthor::Company, (int) $actor->id, $text));
            $this->notices->questionAsked($report, $invitation, $this->views->one($invitation)->testTitle);

            return $this->threads->build($report, ReportMessageAuthor::Company, (int) $actor->id);
        });
    }
}
