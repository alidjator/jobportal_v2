<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewPanel;
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
 * Tells the people involved that an interview changed. The job seeker hears what the team did (scheduled, rescheduled, cancelled, proposal accepted or refused,
 * no-show) and the reminders (the day before and two hours before); the team (the interviewer and who scheduled it, and the whole panel for the reminder) hears what
 * the job seeker did (confirmed, proposed another time, with the reason, declined, withdrew the recording consent) and the day-before reminder. An accepted proposal is
 * a new time, so the panel is invited again as with a reschedule. In-app notifications are always written (ids and the start time only, the reason of a proposal; the
 * team's message with a refusal goes only into the email, never stored); the job seeker's email follows the preference `application_update`, and is sent for every
 * change the job seeker hears of. Scheduled, rescheduled, accepted proposal and details updated carry the `.ics` request, cancelled and declined its cancellation,
 * the others none. The team's emails are sent elsewhere. In-app and email are separate steps, so a retry of one never repeats the other.
 */
final readonly class NotifyOfInterviewChange
{
    public function __construct(
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private InterviewRepository $interviews,
        private InterviewPanel $panel,
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

        if ($event->change === InterviewChange::Proposed && $interview->proposedStartsAt !== null) {
            $data['proposed_starts_at'] = $interview->proposedStartsAt->format(DATE_ATOM);
            $data['reason'] = $interview->proposalReason;
        }

        if ($event->change === InterviewChange::StartingSoon) {
            $data['format'] = $interview->format->value;
        }

        $type = ApplicationNotificationType::forInterview($event->change);

        if ($this->toApplicant($event->change) && $this->contacts->contact($event->applicantUserId) !== null) {
            $this->notifier->send([$event->applicantUserId], $type, $data);
        }

        if ($this->toTeam($event->change)) {
            $this->notifier->send($this->team($event), $type, $data);
        }

        $invited = $this->invited($event);

        if ($invited !== []) {
            $this->notifier->send($invited, ApplicationNotificationType::INTERVIEW_PANEL_INVITED, $data);
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

        $variables = InterviewEmailVariables::of($interview, $contact, $jobTitle, $companyName, $event->companyId);

        if ($event->change === InterviewChange::ProposalRefused) {
            $variables['message'] = $event->message === null ? '' : "\n\n".($contact->locale === 'id' ? 'Pesan dari perusahaan' : 'Message from the company').': '.$event->message;
        }

        $calendar = $method === null ? null : $this->calendar->write($interview, $method, $jobTitle, $companyName, ['name' => $contact->name, 'email' => $contact->email], $this->clock->now());

        $this->emails->send($contact, $key, $variables, $calendar, $method ?? InterviewCalendar::REQUEST);
    }

    /**
     * The members invited to answer: the main interviewer and the panel with a new interview or a new time, the added members with a panel change.
     *
     * @return list<int>
     */
    private function invited(InterviewChanged $event): array
    {
        return match ($event->change) {
            InterviewChange::Scheduled, InterviewChange::Rescheduled, InterviewChange::ProposalAccepted => array_values(array_unique([$event->interviewerId, ...($this->panel->forInterviews([$event->interviewId])[$event->interviewId] ?? [])])),
            InterviewChange::PanelChanged => $event->addedUserIds,
            default => [],
        };
    }

    /** The members told in-app: the main interviewer and who scheduled it; the day-before reminder goes to the whole panel too. @return list<int> */
    private function team(InterviewChanged $event): array
    {
        $members = [$event->interviewerId, $event->createdBy];

        if ($event->change === InterviewChange::Reminder) {
            $members = [...$members, ...($this->panel->forInterviews([$event->interviewId])[$event->interviewId] ?? [])];
        }

        return array_values(array_unique($members));
    }

    /** The calendar file that goes with the email: a new or changed event, or its cancellation (also when the job seeker declined); none with the reminder. */
    private function calendarMethod(InterviewChange $change): ?string
    {
        return match ($change) {
            InterviewChange::Scheduled, InterviewChange::Rescheduled, InterviewChange::ProposalAccepted, InterviewChange::Updated => InterviewCalendar::REQUEST,
            InterviewChange::Cancelled, InterviewChange::Declined => InterviewCalendar::CANCEL,
            default => null,
        };
    }

    private function toApplicant(InterviewChange $change): bool
    {
        return in_array($change, [
            InterviewChange::Scheduled, InterviewChange::Rescheduled, InterviewChange::Cancelled, InterviewChange::ProposalAccepted, InterviewChange::ProposalRefused,
            InterviewChange::NoShow, InterviewChange::Reminder, InterviewChange::StartingSoon, InterviewChange::Updated,
        ], true);
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
