<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Recruitment\Domain\Video\VideoRoomRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A member of the panel lets the waiting candidate in. Admitting twice is harmless; the candidate then skips the lobby on every later join of the interview. */
final readonly class AdmitToVideoInterview
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private VideoSessions $sessions,
        private VideoRoomRepository $rooms,
        private LobbySignal $signal,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-interview-participant`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `not-a-video-interview`, `interview-not-confirmed`, `video-disabled`, `video-window-closed` or `candidate-not-waiting`
     */
    public function execute(Actor $actor, int $applicationId): VideoLobby
    {
        [$lobby, $interviewId] = $this->transactions->run(function () use ($actor, $applicationId): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage, panelOpens: true);
            $interview = $this->sessions->joinable($team->application);

            if (! $this->sessions->isOnPanel($interview, $team->member->userId)) {
                throw new AccessDenied('You are not on the panel of this interview.', 'not-an-interview-participant');
            }

            $room = $this->sessions->room($interview);

            if ($room->isAdmitted()) {
                return [ViewVideoLobby::lobby($room, $this->clock), null];
            }

            if (! $room->isWaiting($this->clock->now())) {
                throw new StateConflict('The candidate is not waiting in the lobby.', 'candidate-not-waiting');
            }

            $this->rooms->admit((int) $interview->id, $team->member->userId, $this->clock->now());

            return [new VideoLobby(LobbySignal::ADMITTED, admittedAt: $this->clock->now()), (int) $interview->id];
        });

        if ($interviewId !== null) {
            $this->signal->changed($interviewId, LobbySignal::ADMITTED);
        }

        return $lobby;
    }
}
