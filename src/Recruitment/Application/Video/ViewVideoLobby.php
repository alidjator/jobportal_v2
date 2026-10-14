<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\ApplicantGuard;
use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Video\VideoRoom;
use Src\Recruitment\Domain\Video\VideoRoomRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The lobby of the video interview of an application. A candidate who asked to enter and polls this keeps their place for another minute; the panel only reads.
 * This is also the fallback when the real-time channel is down.
 */
final readonly class ViewVideoLobby
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
     * @throws StateConflict `no-active-interview`, `not-a-video-interview`, `interview-not-confirmed`, `video-disabled` or `video-window-closed`
     */
    public function asTeam(Actor $actor, int $applicationId): VideoLobby
    {
        return $this->transactions->run(function () use ($actor, $applicationId): VideoLobby {
            $team = $this->lookup->find($actor, $applicationId, permission: CompanyPermission::InterviewsManage);
            $interview = $this->sessions->joinable($team->application);

            if (! $this->sessions->isOnPanel($interview, $team->member->userId)) {
                throw new AccessDenied('You are not on the panel of this interview.', 'not-an-interview-participant');
            }

            return self::lobby($this->sessions->room($interview), $this->clock);
        });
    }

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `not-a-video-interview`, `interview-not-confirmed`, `video-disabled` or `video-window-closed`
     */
    public function asApplicant(Actor $actor, int $applicationId): VideoLobby
    {
        $userId = $this->guard->userId($actor);

        [$lobby, $interviewId] = $this->transactions->run(function () use ($userId, $applicationId): array {
            $application = $this->applications->findOfApplicant($userId, $applicationId, forUpdate: true) ?? throw EntityNotFound::of('Application', $applicationId);
            $interview = $this->sessions->joinable($application);
            $room = $this->sessions->room($interview);
            $now = $this->clock->now();

            // A candidate who joined before and still polls keeps waiting; one who never asked to enter stays idle.
            if (! $room->isAdmitted() && $room->candidateWaitingAt !== null) {
                $this->rooms->markWaiting((int) $interview->id, $now);

                return [new VideoLobby(LobbySignal::WAITING, $now), $room->isWaiting($now) ? null : (int) $interview->id];
            }

            return [self::lobby($room, $this->clock), null];
        });

        if ($interviewId !== null) {
            $this->signal->changed($interviewId, LobbySignal::WAITING);
        }

        return $lobby;
    }

    public static function lobby(VideoRoom $room, Clock $clock): VideoLobby
    {
        return match (true) {
            $room->isAdmitted() => new VideoLobby(LobbySignal::ADMITTED, admittedAt: $room->admittedAt),
            $room->isWaiting($clock->now()) => new VideoLobby(LobbySignal::WAITING, $room->candidateWaitingAt),
            default => new VideoLobby(VideoLobby::IDLE),
        };
    }
}
