<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the people involved that an offer changed. The job seeker hears what the team did (sent, revised, withdrawn) and that the deadline is near; the member
 * who sent the offer hears the answer (accepted, declined). In-app notifications are always written (ids and the deadline only); the job seeker's email follows the
 * preference `application_update`. The team gets no email. In-app and email are separate steps, so a retry of one never repeats the other.
 */
final readonly class NotifyOfOfferChange
{
    public function __construct(
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private OfferRepository $offers,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private ApplicantEmailSender $emails,
    ) {}

    public function inApp(OfferChanged $event): void
    {
        $offer = $this->offers->findById($event->offerId);

        if (! $offer instanceof Offer) {
            return;
        }

        $data = [
            'application_id' => $event->applicationId,
            'offer_id' => $event->offerId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobTitle($event),
            'company_name' => $this->companyName($event),
            'expires_at' => $offer->expiresAt->format(DATE_ATOM),
        ];

        $type = ApplicationNotificationType::forOffer($event->change);

        if ($this->toApplicant($event->change) && $this->contacts->contact($event->applicantUserId) !== null) {
            $this->notifier->send([$event->applicantUserId], $type, $data);
        }

        if (! $this->toApplicant($event->change)) {
            $this->notifier->send([$event->createdBy], $type, $data);
        }
    }

    public function email(OfferChanged $event): void
    {
        $key = RecruitmentEmailTemplates::keyForOffer($event->change);
        $contact = $key === null ? null : $this->contacts->contact($event->applicantUserId);
        $offer = $this->offers->findById($event->offerId);

        if ($key === null || ! $contact instanceof ApplicantContact || ! $offer instanceof Offer) {
            return;
        }

        if (! $this->preferences->isEnabled($event->applicantUserId, ApplicationNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        $this->emails->send($contact, $key, OfferEmailVariables::of($offer, $contact, $this->jobTitle($event), $this->companyName($event), $event->companyId));
    }

    private function toApplicant(OfferChange $change): bool
    {
        return in_array($change, [OfferChange::Sent, OfferChange::Revised, OfferChange::Withdrawn, OfferChange::Expiring], true);
    }

    private function jobTitle(OfferChanged $event): string
    {
        return $this->jobs->titles([$event->jobId])[$event->jobId] ?? '';
    }

    private function companyName(OfferChanged $event): string
    {
        $summary = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;

        return $summary === null ? '' : $summary->name;
    }
}
