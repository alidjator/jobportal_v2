<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The candidate's right to an explanation of a psychological result (UU 23/2022 Pasal 44): available once the report is signed. Another candidate's test is a 404. */
final readonly class GetMyExplanation
{
    public function __construct(
        private InvitationRepository $invitations,
        private AttemptRepository $attempts,
        private AttemptOutcomeRepository $outcomes,
        private ReportRepository $reports,
        private TestRepository $tests,
        private ScaleSummaries $scales,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `report-not-signed`
     */
    public function execute(Actor $actor, int $invitationId): MyExplanation
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can read the explanation of a test.', 'job-seeker-only');
        }

        $invitation = $this->invitations->find($invitationId);

        if (! $invitation instanceof Invitation || $invitation->applicantUserId !== $actor->id) {
            throw EntityNotFound::of('Invitation', $invitationId);
        }

        $attempt = $this->attempts->findByInvitation($invitationId) ?? throw EntityNotFound::of('Report', $invitationId);
        $report = $this->reports->findByAttempt((int) $attempt->id) ?? throw EntityNotFound::of('Report', $invitationId);

        if (! $report->isSigned() || $report->signedAt === null || $report->number === null) {
            throw new StateConflict('A psychologist has not signed the report of this test yet.', 'report-not-signed');
        }

        $result = $this->outcomes->resultOf((int) $attempt->id);

        return new MyExplanation(
            $invitationId,
            $this->tests->find($invitation->testId)?->title() ?? '',
            $report->number,
            $report->signedAt,
            (string) $report->signerName,
            (string) $report->explanation,
            $result?->scaleScores === null ? [] : $this->scales->rows($invitation->testId, $result->scaleScores, $attempt->normCandidate()),
        );
    }
}
