<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\Invitation;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the people involved: the candidate when they are invited (an in-app notification always, and an email when the account is active, the address verified
 * and the `invitation` email preference on), and the member who invited when the candidate declines (in-app, with the reason when there is one).
 */
final readonly class InvitationNotifier
{
    public function __construct(
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private ApplicantContacts $contacts,
        private CompanyDirectory $companies,
        private JobDirectory $jobs,
        private InvitationEmailSender $emails,
    ) {}

    public function invited(Invitation $invitation): void
    {
        $this->notifier->send([$invitation->userId], InvitationNotificationType::RECEIVED, [
            'invitation_id' => $invitation->id, 'job_id' => $invitation->jobId, 'company_id' => $invitation->companyId,
        ]);

        $contact = $this->contacts->contact($invitation->userId);

        if (! $contact instanceof ApplicantContact || ! $contact->active || ! $contact->emailVerified
            || ! $this->preferences->isEnabled($invitation->userId, InvitationNotificationType::EMAIL_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        $this->emails->send($contact, [
            'company_name' => $this->companies->summaries([$invitation->companyId])[$invitation->companyId]->name ?? '',
            'job_title' => $this->jobs->titles([$invitation->jobId])[$invitation->jobId] ?? '',
            'message' => $invitation->note ?? '',
        ]);
    }

    public function declined(Invitation $invitation): void
    {
        $this->notifier->send([$invitation->invitedByUserId], InvitationNotificationType::DECLINED, [
            'invitation_id' => $invitation->id, 'job_id' => $invitation->jobId, 'reason' => $invitation->declineReason,
        ]);
    }
}
