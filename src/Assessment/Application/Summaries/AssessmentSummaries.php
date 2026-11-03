<?php

namespace Src\Assessment\Application\Summaries;

use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\SubmittedAssessment;
use Src\Assessment\Domain\SubmittedAssessmentReader;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Assessment\ApplicationAssessmentSummaries;
use Src\Shared\Application\Assessment\AssessmentSummary;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The summary of the latest submitted test of each application (`docs/design/phase-8-hiring-managers.md` section 4), under the rule of the result page (`GET company/assessment/invitations/{id}/result`):
 * the member sees it with `assessments.send`, or as an assessor of that invitation, or, for a skill test only, as a member of an interview panel of the application; a panel-only member never sees a
 * psychological battery. An application the member may not see at all is left out too.
 */
final readonly class AssessmentSummaries implements ApplicationAssessmentSummaries
{
    public function __construct(
        private SubmittedAssessmentReader $submitted,
        private TestRepository $tests,
        private InvitationRepository $invitations,
        private ReportRepository $reports,
        private TeamApplications $applications,
    ) {}

    public function latestForApplications(Actor $viewer, array $applicationIds): array
    {
        $summaries = [];

        foreach ($this->submitted->latest($applicationIds) as $applicationId => $assessment) {
            $test = $this->tests->find($assessment->testId);

            if (! $test instanceof Test || ! $this->mayRead($viewer, $assessment, $test->kind())) {
                continue;
            }

            $summaries[$applicationId] = $this->summary($assessment, $test);
        }

        return $summaries;
    }

    private function mayRead(Actor $viewer, SubmittedAssessment $assessment, TestKind $kind): bool
    {
        try {
            $application = $this->applications->find($viewer, $assessment->applicationId, panelOpens: true);
        } catch (AccessDenied|EntityNotFound) {
            return false;
        }

        if ($kind === TestKind::Psychological && $application->panelOnly) {
            return false;
        }

        if ($kind === TestKind::Skill && $application->panelOnly) {
            return true;
        }

        $invitation = $this->invitations->find($assessment->invitationId);

        return ($viewer->membership?->can(CompanyPermission::AssessmentsSend) ?? false)
            || ($invitation !== null && $viewer->id !== null && in_array($viewer->id, $invitation->assessorIds, true));
    }

    private function summary(SubmittedAssessment $assessment, Test $test): AssessmentSummary
    {
        if ($test->kind() === TestKind::Skill) {
            return new AssessmentSummary(
                'skill', $test->title(), $assessment->submittedAt, $assessment->status->value ?? 'pending_scoring',
                $assessment->percentage, $assessment->band?->value,
            );
        }

        $report = $assessment->reportNumber === null ? null : $this->reports->findByAttempt($assessment->attemptId);
        $recommendation = $report instanceof Report ? $report->recommendation : null;

        return new AssessmentSummary(
            'psychological', $test->title(), $assessment->submittedAt, $assessment->reportNumber === null ? 'awaiting_psychologist_review' : 'signed',
            recommendation: $recommendation?->value, recommendationLabel: $recommendation?->label(),
        );
    }
}
