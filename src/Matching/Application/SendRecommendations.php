<?php

namespace Src\Matching\Application;

use Src\Matching\Domain\DeliveryRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\JobInvitation\InvitedCandidates;
use Src\Shared\Application\Matching\CandidateSignals;
use Src\Shared\Application\Matching\CandidateSignalSource;
use Src\Shared\Application\Matching\JobSignals;
use Src\Shared\Application\Matching\JobSignalSource;
use Src\Shared\Application\Matching\MatchScore;
use Src\Shared\Application\Matching\MatchScoring;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;
use Src\Shared\Application\Recruitment\ApplicantRelations;

/**
 * The weekly email: for each job seeker with a complete profile, an active account, a verified address and the `recommendation` email preference on, the best
 * live jobs (at most 5, 40% or more) that they have not applied to, were not invited to and were not named in an earlier email. Nothing is sent when there is
 * none, and nobody gets two emails within six days, so running it again the same day sends nothing new.
 */
final readonly class SendRecommendations
{
    /** The most jobs named in one email. */
    public const int MAX_JOBS = 5;

    private const int BATCH = 200;

    private const int POOL = 300;

    /** A person who was sent an email in the last days is left alone. */
    private const string QUIET_PERIOD = '-6 days';

    public function __construct(
        private CandidateSignalSource $candidates,
        private JobSignalSource $jobs,
        private MatchScoring $scoring,
        private ApplicantContacts $contacts,
        private NotificationPreferences $preferences,
        private ApplicantRelations $applications,
        private InvitedCandidates $invitations,
        private DeliveryRepository $deliveries,
        private RecommendationEmailSender $emails,
        private Clock $clock,
    ) {}

    /** @return int how many emails were sent */
    public function execute(): int
    {
        $sent = 0;
        $afterId = 0;

        while (($userIds = $this->candidates->userIdsAfter($afterId, self::BATCH)) !== []) {
            $afterId = max($userIds);

            foreach ($this->candidates->forUsers($userIds) as $candidate) {
                $sent += $this->recommendTo($candidate) ? 1 : 0;
            }
        }

        return $sent;
    }

    private function recommendTo(CandidateSignals $candidate): bool
    {
        $now = $this->clock->now();
        $contact = $this->contacts->contact($candidate->userId);

        if (! $contact instanceof ApplicantContact || ! $contact->active || ! $contact->emailVerified
            || ! $this->preferences->isEnabled($candidate->userId, RecommendationNotificationType::EMAIL_PREFERENCE, NotificationChannel::Email)
            || $this->deliveries->hasSentSince($candidate->userId, $now->modify(self::QUIET_PERIOD))) {
            return false;
        }

        $pool = $this->jobs->liveNear($candidate->provinceId, self::POOL);
        $ids = array_map(static fn (JobSignals $job): int => $job->jobId, $pool);
        $skip = [...$this->applications->appliedJobIds($candidate->userId, $ids), ...$this->invitations->jobIdsOf($candidate->userId, $ids), ...$this->deliveries->sentJobIds($candidate->userId, $ids)];

        /** @var list<array{job: JobSignals, score: MatchScore}> $best */
        $best = [];

        foreach ($pool as $job) {
            if (in_array($job->jobId, $skip, true)) {
                continue;
            }

            $score = $this->scoring->score($candidate, $job);

            if ($score->isRecommended()) {
                $best[] = ['job' => $job, 'score' => $score];
            }
        }

        if ($best === []) {
            return false;
        }

        // The highest percentage first; the pool is newest first and the sort is stable, so a tie keeps the newer job first.
        usort($best, static fn (array $a, array $b): int => $b['score']->percent <=> $a['score']->percent);
        $best = array_slice($best, 0, self::MAX_JOBS);

        $this->deliveries->record($candidate->userId, array_map(static fn (array $entry): int => $entry['job']->jobId, $best), $now);
        $this->emails->send($contact, [
            'job_count' => (string) count($best),
            'jobs' => implode("\n\n", array_map(static fn (array $entry): string => "{$entry['job']->title} ({$entry['score']->percent}% match)", $best)),
        ]);

        return true;
    }
}
