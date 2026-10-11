<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Application\Reports\ReportDocument;
use Src\Assessment\Application\Reports\ReportFile;
use Src\Assessment\Application\Reports\ReportPdfRenderer;
use Src\Assessment\Application\Reports\ScaleSummaries;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The PDF of a signed report, for the members who may see the application. A draft is never handed out. */
final readonly class DownloadReport
{
    public function __construct(
        private VisibleInvitations $visible,
        private AttemptRepository $attempts,
        private AttemptOutcomeRepository $outcomes,
        private ReportRepository $reports,
        private TestRepository $tests,
        private ProviderRepository $providers,
        private ApplicantContacts $accounts,
        private ScaleSummaries $scales,
        private ReportPdfRenderer $renderer,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `report-not-signed`
     */
    public function execute(Actor $actor, int $invitationId): ReportFile
    {
        $invitation = $this->visible->find($actor, $invitationId);
        $attempt = $this->attempts->findByInvitation($invitationId) ?? throw EntityNotFound::of('Report', $invitationId);
        $report = $this->reports->findByAttempt((int) $attempt->id) ?? throw EntityNotFound::of('Report', $invitationId);

        if (! $report->isSigned() || $report->number === null || $report->signedAt === null) {
            throw new StateConflict('The report is not signed yet.', 'report-not-signed');
        }

        return new ReportFile($this->fileName($report), $this->renderer->render($this->document($invitation, $report)));
    }

    private function document(Invitation $invitation, Report $report): ReportDocument
    {
        $test = $this->tests->find($invitation->testId) ?? throw EntityNotFound::of('Test', $invitation->testId);
        $provider = $this->providers->findById($report->providerId) ?? throw EntityNotFound::of('Provider', $report->providerId);
        $result = $this->outcomes->resultOf($report->attemptId);

        return new ReportDocument(
            $provider->name(),
            $provider->legalName(),
            (string) $report->number,
            $test->title(),
            $this->accounts->names([$invitation->applicantUserId])[$invitation->applicantUserId] ?? '',
            $report->body,
            $result?->scaleScores === null ? [] : $this->scales->rows($invitation->testId, $result->scaleScores),
            (string) $report->signerName,
            (string) $report->signerStrNumber,
            (string) $report->signerSilpNumber,
            $report->signedAt ?? throw EntityNotFound::of('Report', (int) $invitation->id),
        );
    }

    /** The report number holds slashes, so the file name replaces them. */
    private function fileName(Report $report): string
    {
        return 'report-'.str_replace('/', '-', (string) $report->number).'.pdf';
    }
}
