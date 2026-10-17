<?php

namespace Src\Recruitment\Application;

use DateTimeZone;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the people involved that an interview changed. The job seeker hears what the team did (scheduled, rescheduled, cancelled, proposal accepted) and the
 * reminder; the team (the interviewer and who scheduled it) hears what the job seeker did (confirmed, proposed another time, declined, withdrew the recording consent) and the reminder.
 * In-app notifications are always written (ids and the start time only); the job seeker's email follows the preference `application_update`, and is sent for
 * scheduled, rescheduled, details updated, cancelled, declined by the job seeker (a cancellation of the calendar event) and the reminder; all but the reminder carry
 * the `.ics` file of the interview. The team gets no email. In-app and email are separate steps, so a retry of one never repeats the other.
 */
final readonly class NotifyOfInterviewChange
{
    private const string EMAIL_ZONE = 'Asia/Jakarta';

    public function __construct(
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private InterviewRepository $interviews,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private InterviewEmailSender $emails,
        private InterviewCalendar $calendar,
        private Clock $clock,
    ) {}

    public function inApp(InterviewChanged $event): void
    {
        $interview = $this->interviews->findById($event->interviewId);

        if (! $interview instanceof Interview) {
            return;
        }

        $data = [
            'application_id' => $event->applicationId,
            'interview_id' => $event->interviewId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobTitle($event),
            'company_name' => $this->companyName($event),
            'starts_at' => $interview->startsAt->format(DATE_ATOM),
        ];

        $type = ApplicationNotificationType::forInterview($event->change);

        if ($this->toApplicant($event->change) && $this->contacts->contact($event->applicantUserId) !== null) {
            $this->notifier->send([$event->applicantUserId], $type, $data);
        }

        if ($this->toTeam($event->change)) {
            $this->notifier->send(array_values(array_unique([$event->interviewerId, $event->createdBy])), $type, $data);
        }
    }

    public function email(InterviewChanged $event): void
    {
        $key = RecruitmentEmailTemplates::keyForInterview($event->change);
        $contact = $key === null ? null : $this->contacts->contact($event->applicantUserId);
        $interview = $this->interviews->findById($event->interviewId);

        if ($key === null || ! $contact instanceof ApplicantContact || ! $interview instanceof Interview) {
            return;
        }

        if (! $this->preferences->isEnabled($event->applicantUserId, ApplicationNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        $jobTitle = $this->jobTitle($event);
        $companyName = $this->companyName($event);
        $method = $this->calendarMethod($event->change);

        $this->emails->send($contact, $key, [
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'note' => $interview->note === null ? '' : "\n\n".($contact->locale === 'id' ? 'Catatan' : 'Note').': '.$interview->note,
            'starts_at' => $interview->startsAt->setTimezone(new DateTimeZone(self::EMAIL_ZONE))->format('Y-m-d H:i').' WIB',
            'format' => match ($interview->format) {
                InterviewFormat::Online => 'Online',
                InterviewFormat::Onsite => 'Onsite',
                InterviewFormat::Video => 'Video in the platform',
                InterviewFormat::Voice => 'Phone call',
            },
            'place' => match ($interview->format) {
                InterviewFormat::Online => (string) $interview->meetingUrl,
                InterviewFormat::Onsite => (string) $interview->location,
                InterviewFormat::Video => 'In the platform: open your application to join',
                InterviewFormat::Voice => 'We will call you on the number you confirmed',
            },
            'recording' => $interview->recorded ? 'Yes, only if you agree' : 'No',
        ], $method === null ? null : $this->calendar->write($interview, $method, $jobTitle, $companyName, ['name' => $contact->name, 'email' => $contact->email], $this->clock->now()), $method ?? InterviewCalendar::REQUEST);
    }

    /** The calendar file that goes with the email: a new or changed event, or its cancellation (also when the job seeker declined); none with the reminder. */
    private function calendarMethod(InterviewChange $change): ?string
    {
        return match ($change) {
            InterviewChange::Scheduled, InterviewChange::Rescheduled, InterviewChange::Updated => InterviewCalendar::REQUEST,
            InterviewChange::Cancelled, InterviewChange::Declined => InterviewCalendar::CANCEL,
            default => null,
        };
    }

    private function toApplicant(InterviewChange $change): bool
    {
        return in_array($change, [InterviewChange::Scheduled, InterviewChange::Rescheduled, InterviewChange::Cancelled, InterviewChange::ProposalAccepted, InterviewChange::Reminder, InterviewChange::Updated], true);
    }

    private function toTeam(InterviewChange $change): bool
    {
        return in_array($change, [InterviewChange::Confirmed, InterviewChange::Proposed, InterviewChange::Declined, InterviewChange::Reminder, InterviewChange::RecordingWithdrawn], true);
    }

    private function jobTitle(InterviewChanged $event): string
    {
        return $this->jobs->titles([$event->jobId])[$event->jobId] ?? '';
    }

    private function companyName(InterviewChanged $event): string
    {
        $summary = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;

        return $summary === null ? '' : $summary->name;
    }
}
