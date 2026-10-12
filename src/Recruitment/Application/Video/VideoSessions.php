<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\Video\VideoJoinWindow;
use Src\Recruitment\Domain\Video\VideoRoom;
use Src\Recruitment\Domain\Video\VideoRoomRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * What every video action needs: the interview of the application checked to be joinable now, who is on its panel, and the credentials of a join. A room is
 * created lazily by the first join inside the window. Everything here runs inside the caller's transaction with the application row locked.
 */
final readonly class VideoSessions
{
    /** The panel (at most 4 members) plus the candidate. */
    private const int MAX_PARTICIPANTS = 5;

    public function __construct(
        private InterviewRepository $interviews,
        private InterviewPanel $panel,
        private VideoRoomRepository $rooms,
        private VideoRoomGateway $gateway,
        private JoinTokenFactory $tokens,
        private IceServerProvider $iceServers,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * The active interview of the application when it is a confirmed video interview inside its join window and video interviews are switched on.
     *
     * @throws StateConflict `no-active-interview`, `not-a-video-interview`, `interview-not-confirmed`, `video-disabled` or `video-window-closed`
     */
    public function joinable(Application $application): Interview
    {
        $interview = $this->interviews->active($application->id ?? 0, forUpdate: true) ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');

        if ($interview->format !== InterviewFormat::Video) {
            throw new StateConflict('This interview is not held in the platform.', 'not-a-video-interview');
        }

        if ($interview->status !== InterviewStatus::Confirmed) {
            throw new StateConflict('Only a confirmed interview can be joined.', 'interview-not-confirmed');
        }

        if (! $this->settings->boolean(InterviewSettings::VIDEO_ENABLED, $application->companyId)) {
            throw new StateConflict('Interviews in the platform are switched off.', 'video-disabled');
        }

        if (! VideoJoinWindow::isOpen($interview, $this->clock->now())) {
            throw $this->windowClosed();
        }

        return $interview;
    }

    /** The main interviewer or a member of the panel. */
    public function isOnPanel(Interview $interview, int $userId): bool
    {
        return $interview->interviewerId === $userId || in_array($userId, $this->panel->forInterviews([(int) $interview->id])[(int) $interview->id] ?? [], true);
    }

    /** The video state of an interview that exists. */
    public function room(Interview $interview): VideoRoom
    {
        return $this->rooms->find((int) $interview->id) ?? throw EntityNotFound::of('Interview', (int) $interview->id);
    }

    /**
     * Gives the user a token for the room, creating the room first when this is the first join.
     *
     * @throws StateConflict `video-window-closed` or `video-capacity-full`
     * @throws ExternalServiceUnavailable
     */
    public function credentials(Interview $interview, int $userId, int $companyId): VideoCredentials
    {
        $interviewId = (int) $interview->id;
        $room = $this->room($interview);

        if ($room->closedAt !== null) {
            throw $this->windowClosed();
        }

        $created = null;

        try {
            if (! $room->isOpen()) {
                $room = $this->openRoom($interviewId, $companyId);
                $created = $room;
            }

            $roomId = (int) $room->roomId;
            $token = $this->tokens->make($interviewId, $roomId, $userId);
            $this->gateway->addToken($token);
            $this->rooms->rememberToken($interviewId, $userId, $this->clock->now());
        } catch (Throwable $failure) {
            // The transaction rolls the new room back, so it must not stay on the video server.
            if ($created instanceof VideoRoom) {
                $this->discard($created);
            }

            throw $failure;
        }

        $expiresAt = VideoJoinWindow::closesAt($interview);

        return new VideoCredentials($roomId, (string) $room->pin, $token, $this->tokens->display($interviewId, $roomId, $userId), $this->gateway->websocketUrl(), $this->iceServers->forUser($userId, $expiresAt), $expiresAt);
    }

    /** @throws StateConflict `video-capacity-full` */
    private function openRoom(int $interviewId, int $companyId): VideoRoom
    {
        $this->rooms->lockCapacity();

        if ($this->rooms->openRoomCount() >= $this->settings->integer(InterviewSettings::VIDEO_MAX_CONCURRENT_ROOMS, $companyId)) {
            throw new StateConflict('Too many video interviews are running right now. Try again in a few minutes.', 'video-capacity-full');
        }

        $pin = bin2hex(random_bytes(8));
        $secret = bin2hex(random_bytes(16));
        $roomId = $this->gateway->createRoom($pin, $secret, self::MAX_PARTICIPANTS);
        $this->rooms->open($interviewId, $roomId, $pin, $secret);

        return new VideoRoom($interviewId, $roomId, $pin, $secret, null, null, null, null);
    }

    private function discard(VideoRoom $room): void
    {
        try {
            $this->gateway->destroyRoom((int) $room->roomId, (string) $room->secret);
        } catch (ExternalServiceUnavailable) {
            // Janus keeps no room after a restart; the original failure is the one to report.
        }
    }

    private function windowClosed(): StateConflict
    {
        return new StateConflict(
            'The video room is open from '.VideoJoinWindow::MINUTES_BEFORE.' minutes before the interview until '.VideoJoinWindow::MINUTES_AFTER.' minutes after it ends.',
            'video-window-closed',
        );
    }
}
