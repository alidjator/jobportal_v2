<?php

namespace Src\JobSeeker\Application\Reminders;

use DateTimeImmutable;
use DateTimeZone;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\ProfileCompleteness;
use Src\JobSeeker\Domain\ProfileReminderRepository;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\Shared\Application\Accounts\FirstSignIns;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\JobAlert\AlertMatchCounts;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Reminds job seekers by email to complete the profile they need to apply: the first one on the day after the first sign-in, then every 7 days, three in all
 * (`ProfileReminderRepository::MAX_REMINDERS`, never reset). Days are Jakarta days, so a daily run at the same hour never slips a day. Nothing is sent once the
 * profile is complete, when the email is not verified or the account is not active, or when the job seeker switched the `profile_reminder` preference off;
 * the preference is looked at again at the next run, but the three-reminder cap only counts reminders that were sent.
 */
final readonly class SendProfileReminders
{
    private const string ZONE = 'Asia/Jakarta';

    private const int BATCH = 200;

    private const int REPEAT_AFTER_DAYS = 7;

    public function __construct(
        private FirstSignIns $signIns,
        private ProfileReminderRepository $reminders,
        private ProfileRepository $profiles,
        private ApplicantContacts $contacts,
        private NotificationPreferences $preferences,
        private AlertMatchCounts $alertMatches,
        private ProfileReminderEmailSender $emails,
        private Clock $clock,
    ) {}

    /** @return int how many reminders were sent */
    public function execute(): int
    {
        $now = $this->clock->now();
        $today = $now->setTimezone(new DateTimeZone(self::ZONE))->setTime(0, 0);
        // Signed in for the first time before today (Jakarta), and last reminded on a day at least 7 days ago.
        $firstSignInBefore = $today->setTimezone(new DateTimeZone('UTC'));
        $lastRemindedBefore = $today->modify('-'.(self::REPEAT_AFTER_DAYS - 1).' days')->setTimezone(new DateTimeZone('UTC'));

        $sent = 0;
        $afterId = 0;

        while (($ids = $this->signIns->jobSeekerIdsBefore($firstSignInBefore, $afterId, self::BATCH)) !== []) {
            $afterId = max($ids);

            foreach ($this->contacts->contacts($this->reminders->dueAmong($ids, $lastRemindedBefore)) as $userId => $contact) {
                $sent += $this->remind($userId, $contact, $now) ? 1 : 0;
            }
        }

        return $sent;
    }

    private function remind(int $userId, ApplicantContact $contact, DateTimeImmutable $now): bool
    {
        if (! $contact->active || ! $contact->emailVerified) {
            return false;
        }

        $counts = $this->profiles->counts($userId);
        $completeness = ProfileCompleteness::evaluate($this->profiles->find($userId) ?? JobSeekerProfile::empty($userId), $counts['experiences'], $counts['educations'], $counts['resumes']);

        if ($completeness->isComplete() || ! $this->preferences->isEnabled($userId, ProfileReminderNotificationType::EMAIL_PREFERENCE, NotificationChannel::Email)) {
            return false;
        }

        $this->emails->send($contact, [
            'missing' => ProfileReminderWords::missing($completeness->missing, $contact->locale),
            'matching_jobs' => ProfileReminderWords::matchingJobs($this->alertMatches->liveJobCount($userId), $contact->locale),
        ]);
        $this->reminders->markSent($userId, $now);

        return true;
    }
}
