<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptResult;
use Src\Assessment\Domain\Events\ReportSigned;
use Src\Assessment\Domain\IntegrityTier;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\NormRepository;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\ReportRenderer;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\ReportTemplateRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;

/**
 * Issues the report of a submitted psychological attempt at once, when the battery asks for it: numbered, signed under the responsible psychologist of the battery
 * (a copy of their name and licence numbers) and given the explanation made from the battery's template. The report falls back to the manual queue (it stays a draft
 * for a psychologist) when anything stands in the way: the provider no longer allows it or is not active, the responsible psychologist is not active, not of the
 * provider or has no valid STR and SILP that day, the attempt's integrity tier is major, a drawing was uploaded, or the explanation cannot be written in full (a scale
 * has no norm, a norm range has no explanation, the template is missing). Runs inside the caller's transaction, after the draft; returns the event to dispatch.
 */
final readonly class AutoIssueReport
{
    public function __construct(
        private ReportRepository $reports,
        private ProviderRepository $providers,
        private PsychologistRepository $psychologists,
        private PsychologistAccounts $psychologistAccounts,
        private ReportTemplateRepository $templates,
        private NormRepository $norms,
        private AnswerRepository $answers,
        private ApplicantContacts $applicants,
        private Clock $clock,
    ) {}

    public function issue(Attempt $attempt, Invitation $invitation, Test $test, AttemptResult $result): ?ReportSigned
    {
        $providerId = $test->owner->providerId;
        $responsibleId = $test->responsiblePsychologistId();

        if ($test->kind() !== TestKind::Psychological || ! $test->autoIssue() || $responsibleId === null || $providerId === null || $attempt->id === null) {
            return null;
        }

        if ($result->integrityTier === IntegrityTier::Major || $result->scaleScores === null || $this->answers->drawingsOf($attempt->id) !== []) {
            return null;
        }

        $provider = $this->providers->findById($providerId, forUpdate: true);
        $responsible = $this->psychologists->findById($responsibleId);
        $report = $this->reports->findByAttempt($attempt->id, forUpdate: true);
        $now = $this->clock->now();

        if ($provider === null || ! $provider->isActive() || ! $provider->autoIssueAllowed() || $report === null || $report->isSigned()) {
            return null;
        }

        if ($responsible === null || $responsible->providerId !== $providerId || ! $responsible->canSignReports($now)) {
            return null;
        }

        $template = $this->templates->explanationTemplateOf((int) $test->id);

        if ($template === null) {
            return null;
        }

        $year = (int) $now->format('Y');
        $sequence = $this->reports->nextSequence($providerId, $year);
        $number = sprintf('%s/%d/%05d', $provider->code(), $year, $sequence);
        $signedOn = $now->format('Y-m-d');
        $signer = $this->psychologistAccounts->summaries([$responsible->userId]);
        $signerName = isset($signer[$responsible->userId]) ? $signer[$responsible->userId]->name : '';
        $candidate = $this->applicants->names([$invitation->applicantUserId])[$invitation->applicantUserId] ?? '';

        $explanation = ReportRenderer::explanation($template, $candidate, $test->title(), $number, $signedOn, $signerName, $result->scaleScores, $this->norms->ofTest((int) $test->id));

        if ($explanation === null) {
            return null;
        }

        $issued = $report->issueAutomatically(ReportRenderer::signed($report->body, $number, $signedOn, $signerName), $explanation, $number, $year, $sequence, $responsible, $signerName, $now);
        $this->reports->update($issued);

        return new ReportSigned(
            (int) $issued->id, $number, (int) $invitation->id, $invitation->applicationId, $invitation->jobId, $invitation->companyId, $providerId, $responsible->userId, null, null, $now,
            autoIssued: true,
        );
    }
}
