<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Recruitment\Domain\RecordingConsent;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A member of the panel starts recording the video interview. Only an interview marked as recorded whose job seeker consented can be recorded, and only once at a time. */
final readonly class StartRecording
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private VideoSessions $sessions,
        private RecordingRepository $recordings,
        private VideoRoomGateway $gateway,
        private RecordingVolume $volume,
        private RecordingSignal $signal,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-interview-participant`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `not-a-video-interview`, `interview-not-confirmed`, `video-disabled`, `video-window-closed`, `recording-not-allowed`, `video-room-not-open` or `recording-in-progress`
     * @throws ExternalServiceUnavailable
     */
    public function execute(Actor $actor, int $applicationId): InterviewRecording
    {
        $recording = $this->transactions->run(function () use ($actor, $applicationId): InterviewRecording {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage);
            $interview = $this->sessions->joinable($team->application);

            if (! $this->sessions->isOnPanel($interview, $team->member->userId)) {
                throw new AccessDenied('You are not on the panel of this interview.', 'not-an-interview-participant');
            }

            if (! $interview->recorded || $interview->recordingConsent !== RecordingConsent::Granted) {
                throw new StateConflict('This interview may not be recorded: the job seeker has not given their consent.', 'recording-not-allowed');
            }

            $room = $this->sessions->room($interview);

            if (! $room->isOpen()) {
                throw new StateConflict('The video room is not open yet.', 'video-room-not-open');
            }

            if ($this->recordings->active((int) $interview->id, forUpdate: true) instanceof InterviewRecording) {
                throw new StateConflict('This interview is already being recorded.', 'recording-in-progress');
            }

            $saved = $this->recordings->add(InterviewRecording::start((int) $interview->id, $team->member->userId, $this->clock->now()));
            // A failure here rolls the new row back, so a recording that never started leaves nothing behind.
            $this->gateway->startRecording((int) $room->roomId, (string) $room->secret, $this->volume->rawDirectory((int) $saved->id));

            return $saved;
        });

        $this->signal->changed($recording->interviewId, true);

        return $recording;
    }
}
