<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptResult;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\NormRepository;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportRenderer;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\ReportTemplateRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Job\JobDirectory;

/**
 * Builds the draft report of a submitted psychological attempt from the test's report template, the raw scores and the norms, and leaves it for the
 * provider's psychologists to review. A report that exists is rebuilt only while nobody edited it. Runs inside the caller's transaction.
 */
final readonly class ReportDrafter
{
    /** The text of a draft when the test somehow has no template: the title and the candidate are still there for the psychologist to write on. */
    private const string FALLBACK_TEMPLATE = '{{test_title}} - {{candidate_name}}';

    public function __construct(
        private ReportRepository $reports,
        private ReportTemplateRepository $templates,
        private NormRepository $norms,
        private ApplicantContacts $accounts,
        private JobDirectory $jobs,
    ) {}

    public function draft(Attempt $attempt, Invitation $invitation, Test $test, AttemptResult $result): void
    {
        $providerId = $test->owner->providerId;

        if ($test->kind() !== TestKind::Psychological || $providerId === null || $attempt->id === null) {
            return;
        }

        $body = ReportRenderer::draft(
            $this->templates->bodyOf((int) $test->id) ?? self::FALLBACK_TEMPLATE,
            $this->accounts->names([$invitation->applicantUserId])[$invitation->applicantUserId] ?? '',
            $test->title(),
            $result->scaleScores ?? [],
            $this->norms->ofTest((int) $test->id),
            $attempt->normCandidate(),
            $this->jobs->titles([$invitation->jobId])[$invitation->jobId] ?? null,
        );

        $existing = $this->reports->findByAttempt($attempt->id, forUpdate: true);

        if ($existing instanceof Report) {
            $this->reports->update($existing->redrafted($body));

            return;
        }

        $this->reports->add(Report::draft($attempt->id, $providerId, $body));
    }
}
