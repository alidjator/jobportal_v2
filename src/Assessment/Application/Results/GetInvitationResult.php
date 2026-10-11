<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Application\Delivery\InvitationViews;
use Src\Assessment\Application\Reports\ScaleSummaries;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The result of one test for the members who may see the application. Before the attempt is submitted there is none. */
final readonly class GetInvitationResult
{
    public function __construct(
        private VisibleInvitations $visible,
        private InvitationViews $views,
        private AttemptRepository $attempts,
        private AttemptOutcomeRepository $outcomes,
        private ReportRepository $reports,
        private ScaleSummaries $scales,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $invitationId): InvitationResult
    {
        $invitation = $this->visible->find($actor, $invitationId);
        $view = $this->views->one($invitation);
        $attempt = $this->attempts->findByInvitation($invitationId);

        if ($attempt === null || ! $attempt->isSubmitted()) {
            return new InvitationResult($view, $attempt, null, null, []);
        }

        $result = $this->outcomes->resultOf((int) $attempt->id);
        $report = $view->testKind === TestKind::Psychological ? $this->reports->findByAttempt((int) $attempt->id) : null;
        $scales = $report !== null && $report->isSigned() && $result?->scaleScores !== null ? $this->scales->rows($invitation->testId, $result->scaleScores) : [];

        return new InvitationResult($view, $attempt, $result, $report, $scales);
    }
}
