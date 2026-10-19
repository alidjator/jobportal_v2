<?php

namespace Src\Shared\Application\Assessment;

use Src\Shared\Application\Applicant\ApplicantContact;
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
     * The template and variables of the email that sending this test would send, for a preview: the request is checked like a real one and nothing is saved.
     *
     * @return array{key: string, variables: array<string, string>}
     *
     * @throws BusinessRuleViolation `invalid-assessment-test`, `invalid-assessment-schedule`, `invalid-assessor` or `message-link-not-allowed`
     */
    public function previewEmail(int $companyId, int $jobId, AssessmentInvitationInput $input, ApplicantContact $contact, string $jobTitle, string $companyName): array;

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
     * The applications of the company whose candidate was sent a test and has not finished it (invited or started); `$jobIds` limits them to those jobs (null: every
     * job). For the pipeline filter `test_pending`.
     *
     * @param  list<int>|null  $jobIds
     * @return list<int>
     */
    public function applicationIdsWithPendingTest(int $companyId, ?array $jobIds): array;

    /**
     * Cancels the active invitation, if any.
     *
     * @return list<DomainEvent>
     */
    public function cancelActive(InvitedApplication $application): array;
}
