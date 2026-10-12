<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\ApplicantGuard;
use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Video\VideoRoomRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Joins the video interview of an application. The interviewer and the panel get credentials at once; the job seeker waits in the lobby until a member of the
 * panel admits them, and gets credentials once admitted (and on every later join of the same interview).
 */
final readonly class JoinVideoInterview
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private VideoSessions $sessions,
        private VideoRoomRepository $rooms,
        private LobbySignal $signal,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-interview-participant`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `not-a-video-interview`, `interview-not-confirmed`, `video-disabled`, `video-window-closed` or `video-capacity-full`
     * @throws ExternalServiceUnavailable
     */
    public function asTeam(Actor $actor, int $applicationId): VideoJoinResult
    {
        return $this->transactions->run(function () use ($actor, $applicationId): VideoJoinResult {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true);
            $interview = $this->sessions->joinable($team->application);

            if (! $this->sessions->isOnPanel($interview, $team->member->userId)) {
                throw new AccessDenied('You are not on the panel of this interview.', 'not-an-interview-participant');
            }

            return new VideoJoinResult(VideoJoinResult::READY, $this->sessions->credentials($interview, $team->member->userId, $team->member->companyId));
        });
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `not-a-video-interview`, `interview-not-confirmed`, `video-disabled`, `video-window-closed` or `video-capacity-full`
     * @throws ExternalServiceUnavailable
     */
    public function asApplicant(Actor $actor, int $applicationId): VideoJoinResult
    {
        $userId = $this->guard->userId($actor);

        [$result, $interviewId] = $this->transactions->run(function () use ($userId, $applicationId): array {
            $application = $this->applications->findOfApplicant($userId, $applicationId, forUpdate: true) ?? throw EntityNotFound::of('Application', $applicationId);
            $interview = $this->sessions->joinable($application);
            $room = $this->sessions->room($interview);

            if ($room->isAdmitted()) {
                return [new VideoJoinResult(VideoJoinResult::READY, $this->sessions->credentials($interview, $userId, $application->companyId)), null];
            }

            $now = $this->clock->now();
            $this->rooms->markWaiting((int) $interview->id, $now);

            // Only a candidate who was not already waiting is news to the panel.
            return [new VideoJoinResult(VideoJoinResult::WAITING), $room->isWaiting($now) ? null : (int) $interview->id];
        });

        if ($interviewId !== null) {
            $this->signal->changed($interviewId, LobbySignal::WAITING);
        }

        return $result;
    }
}
