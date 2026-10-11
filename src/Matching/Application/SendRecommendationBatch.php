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
 * One batch of the weekly email: for each job seeker with a complete profile, an active account, a verified address and the `recommendation` email preference on,
 * the best live jobs (at most 5, 40% or more) that they have not applied to, were not invited to and were not named in an earlier email. Nothing is sent when
 * there is none, and nobody gets two emails within six days, so running a batch again sends nothing new.
 *
 * A batch reads contacts, preferences, earlier emails and applications once for all its job seekers, and loads the pool of live jobs once per province.
 */
final readonly class SendRecommendationBatch
{
    /** The most jobs named in one email. */
    public const int MAX_JOBS = 5;

    public const int BATCH = 200;

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

    public function execute(int $afterId): RecommendationBatch
    {
        $userIds = $this->candidates->userIdsAfter($afterId, self::BATCH);

        if ($userIds === []) {
            return new RecommendationBatch(0, null);
        }

        $next = count($userIds) === self::BATCH ? max($userIds) : null;
        $now = $this->clock->now();

        $contacts = $this->contacts->contacts($userIds);
        $eligible = array_keys(array_filter($contacts, static fn (ApplicantContact $contact): bool => $contact->active && $contact->emailVerified));
        $eligible = $this->preferences->enabledAmong($eligible, RecommendationNotificationType::EMAIL_PREFERENCE, NotificationChannel::Email);
        $eligible = array_values(array_diff($eligible, $this->deliveries->userIdsSentSince($eligible, $now->modify(self::QUIET_PERIOD))));

        /** @var array<int, list<JobSignals>> $pools by province id, 0 for no province */
        $pools = [];
        $candidates = $eligible === [] ? [] : $this->candidates->forUsers($eligible);

        foreach ($candidates as $candidate) {
            $pools[$candidate->provinceId ?? 0] ??= $this->jobs->liveNear($candidate->provinceId, self::POOL);
        }

        $jobIds = array_values(array_unique(array_map(static fn (JobSignals $job): int => $job->jobId, array_merge(...array_values($pools) ?: [[]]))));
        $candidateIds = array_keys($candidates);
        $applied = $this->applications->appliedJobIdsOf($candidateIds, $jobIds);
        $invited = $this->invitations->jobIdsOfUsers($candidateIds, $jobIds);
        $named = $this->deliveries->sentJobIdsOf($candidateIds, $jobIds);

        $sent = 0;

        foreach ($candidates as $candidate) {
            $skip = [...($applied[$candidate->userId] ?? []), ...($invited[$candidate->userId] ?? []), ...($named[$candidate->userId] ?? [])];

            $sent += $this->recommendTo($candidate, $contacts[$candidate->userId], $pools[$candidate->provinceId ?? 0], $skip) ? 1 : 0;
        }

        return new RecommendationBatch($sent, $next);
    }

    /**
     * @param  list<JobSignals>  $pool
     * @param  list<int>  $skip
     */
    private function recommendTo(CandidateSignals $candidate, ApplicantContact $contact, array $pool, array $skip): bool
    {
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

        $this->deliveries->record($candidate->userId, array_map(static fn (array $entry): int => $entry['job']->jobId, $best), $this->clock->now());
        $this->emails->send($contact, [
            'job_count' => (string) count($best),
            'jobs' => implode("\n\n", array_map(static fn (array $entry): string => "{$entry['job']->title} ({$entry['score']->percent}% match)", $best)),
        ]);

        return true;
    }
}
