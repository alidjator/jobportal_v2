<?php

namespace Src\Assessment\Application\Delivery;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Application\Assessment\InvitedApplication;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Sends a test to the candidate of an application that is in an assessment stage, or replaces the active invitation with another test. The candidate is told. */
final readonly class SendInvitation
{
    public function __construct(
        private TeamApplications $applications,
        private InvitationPlacer $placer,
        private InvitationViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
    ) {}

    /**
     * @return array{view: InvitationView, replaced: bool}
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `job-taken-down`, `not-in-assessment-stage` or `invitation-in-progress`
     * @throws BusinessRuleViolation `invalid-assessment-test`, `invalid-assessment-schedule` or `invalid-assessor`
     */
    public function execute(Actor $actor, int $applicationId, AssessmentInvitationInput $input): array
    {
        $placed = $this->transactions->run(function () use ($actor, $applicationId, $input): PlacedInvitation {
            $application = $this->applications->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::AssessmentsSend);

            if ($application->jobTakenDown) {
                throw new StateConflict('This job was taken down, so its applications can no longer be processed.', 'job-taken-down');
            }

            if (! $application->inAssessmentStage) {
                throw new StateConflict('Move the application to an assessment stage before sending a test.', 'not-in-assessment-stage');
            }

            return $this->placer->place(new InvitedApplication($applicationId, $application->companyId, $application->jobId, $application->applicantUserId), (int) $actor->id, $input);
        });

        $this->events->dispatch([$placed->event]);

        return ['view' => $this->views->one($placed->invitation), 'replaced' => $placed->replaced];
    }
}
