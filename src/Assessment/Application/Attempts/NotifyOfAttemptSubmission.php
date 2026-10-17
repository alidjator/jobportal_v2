<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Assessment\Application\Delivery\AssessmentNotificationType;
use Src\Assessment\Domain\Events\AttemptSubmitted;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the team that a test was submitted. In the app, the member who sent it and the assessors; by email (preference `team_scoring`, off until switched on), the assessors only. The data holds ids
 * and titles, no answers and no candidate name.
 */
final readonly class NotifyOfAttemptSubmission
{
    public function __construct(
        private InvitationRepository $invitations,
        private TestRepository $tests,
        private JobDirectory $jobs,
        private InAppNotifier $notifier,
        private CompanyDirectory $companies,
        private CompanyMemberAccounts $accounts,
        private NotificationPreferences $preferences,
        private TeamSubmissionSender $emails,
    ) {}

    public const string PREFERENCE = 'team_scoring';

    public function inApp(AttemptSubmitted $event): void
    {
        $invitation = $this->invitations->find($event->invitationId);
        $test = $invitation instanceof Invitation ? $this->tests->find($invitation->testId) : null;

        if (! $invitation instanceof Invitation || ! $test instanceof Test) {
            return;
        }

        $this->notifier->send(array_values(array_unique([$invitation->invitedByUserId, ...$invitation->assessorIds])), AssessmentNotificationType::SUBMITTED, [
            'application_id' => $event->applicationId,
            'invitation_id' => $event->invitationId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
            'test_title' => $test->title(),
            'auto_submitted' => $event->auto,
        ]);
    }

    public function email(AttemptSubmitted $event): void
    {
        $invitation = $this->invitations->find($event->invitationId);
        $test = $invitation instanceof Invitation ? $this->tests->find($invitation->testId) : null;

        if (! $invitation instanceof Invitation || ! $test instanceof Test) {
            return;
        }

        $wanted = $this->preferences->enabledAmong(array_values(array_unique($invitation->assessorIds)), self::PREFERENCE, NotificationChannel::Email);

        if ($wanted === []) {
            return;
        }

        $company = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;

        foreach ($this->accounts->summaries($wanted) as $account) {
            if ($account->status === 'active') {
                $this->emails->send($account->email, $account->locale, AssessmentEmailTemplates::TEAM_TEST_SUBMITTED, [
                    'company_id' => (string) $event->companyId,
                    'name' => $account->name,
                    'job_title' => $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
                    'company_name' => $company === null ? '' : $company->name,
                    'test_title' => $test->title(),
                ]);
            }
        }
    }
}
