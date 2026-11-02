<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Gathers what the PDF of a signed report prints: the provider, the test, the candidate's name, the scales with their norms, the signer and the address where the number can be checked. */
final readonly class ReportDocumentBuilder
{
    public function __construct(
        private AttemptRepository $attempts,
        private InvitationRepository $invitations,
        private TestRepository $tests,
        private ProviderRepository $providers,
        private AttemptOutcomeRepository $outcomes,
        private ApplicantContacts $accounts,
        private ScaleSummaries $scales,
        private ReportVerificationLinks $links,
    ) {}

    /** @throws EntityNotFound */
    public function build(Report $report): ReportDocument
    {
        $attempt = $this->attempts->find($report->attemptId) ?? throw EntityNotFound::of('Attempt', $report->attemptId);
        $invitation = $this->invitations->find($attempt->invitationId) ?? throw EntityNotFound::of('Invitation', $attempt->invitationId);
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
            $result?->scaleScores === null ? [] : $this->scales->rows($invitation->testId, $result->scaleScores, $attempt->normCandidate()),
            (string) $report->signerName,
            (string) $report->signerStrNumber,
            (string) $report->signerSilpNumber,
            $report->signedAt ?? throw EntityNotFound::of('Report', (int) $report->id),
            $report->autoIssued && $report->revision === 0,
            $report->recommendation?->label(),
            $this->links->forNumber((string) $report->number),
        );
    }
}
