<?php

namespace Src\Shared\Application\Assessment;

use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Lets Recruitment send, keep and cancel the tests of an application without reading Assessment's tables. Provided by Assessment. Every method that
 * changes something runs inside the caller's transaction, with the application row already locked, and returns the events the caller dispatches after
 * the commit.
 */
interface AssessmentInvitations
{
    /**
     * Checks a request without saving anything (a bulk move checks the shared input once before it starts).
     *
     * @throws BusinessRuleViolation `invalid-assessment-test`, `invalid-assessment-schedule` or `invalid-assessor`
     */
    public function assertCanInvite(int $companyId, int $jobId, AssessmentInvitationInput $input): void;

    /**
     * Sends the test and cancels the active invitation it replaces.
     *
     * @return list<DomainEvent>
     *
     * @throws BusinessRuleViolation `invalid-assessment-test`, `invalid-assessment-schedule` or `invalid-assessor`
     * @throws StateConflict `invitation-in-progress` when the candidate already started the active test
     */
    public function invite(InvitedApplication $application, int $invitedByUserId, AssessmentInvitationInput $input): array;

    /** Whether the application has an active test (invited or started). */
    public function hasActive(int $applicationId): bool;

    /**
     * Cancels the active invitation, if any.
     *
     * @return list<DomainEvent>
     */
    public function cancelActive(InvitedApplication $application): array;
}
