<?php

namespace Src\Recruitment\Application\Voice;

use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Recruitment\Domain\RecordingConsent;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingMedia;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Voice\CallRepository;
use Src\Recruitment\Domain\Voice\CallStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * A member of the panel starts recording the phone call in progress. Only an interview marked as recorded whose job seeker consented can be recorded, only while
 * the candidate is on the line (so the announcement that the call is recorded is heard) and only once at a time. The recording ends with the call.
 */
final readonly class StartVoiceRecording
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private VoiceSessions $sessions,
        private CallRepository $calls,
        private RecordingRepository $recordings,
        private VoiceLimits $limits,
        private VoiceGateway $gateway,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-interview-participant`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `not-a-voice-interview`, `interview-not-confirmed`, `voice-disabled`, `no-phone-number`, `voice-window-closed`, `recording-not-allowed`, `no-live-call` or `recording-in-progress`
     * @throws ExternalServiceUnavailable
     */
    public function execute(Actor $actor, int $applicationId): InterviewRecording
    {
        return $this->transactions->run(function () use ($actor, $applicationId): InterviewRecording {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage);
            $interview = $this->sessions->callable($team->application);

            if (! $this->sessions->isOnPanel($interview, $team->member->userId)) {
                throw new AccessDenied('You are not on the panel of this interview.', 'not-an-interview-participant');
            }

            if (! $interview->recorded || $interview->recordingConsent !== RecordingConsent::Granted) {
                throw new StateConflict('This interview may not be recorded: the job seeker has not given their consent.', 'recording-not-allowed');
            }

            $call = $this->calls->liveOf((int) $interview->id);

            if ($call === null || $call->status !== CallStatus::Answered) {
                throw new StateConflict('The candidate must be on the line to start the recording.', 'no-live-call');
            }

            if ($this->recordings->active((int) $interview->id, forUpdate: true) instanceof InterviewRecording) {
                throw new StateConflict('This interview is already being recorded.', 'recording-in-progress');
            }

            $recording = $this->recordings->add(InterviewRecording::start((int) $interview->id, $team->member->userId, $this->clock->now(), RecordingMedia::Audio));
            $name = VoiceNames::recording((int) $recording->id);
            $bridgeId = VoiceNames::bridge((int) $interview->id);

            // A failure rolls the new row back, so a recording that never started leaves nothing behind; one that started but was not announced is stopped first.
            $this->gateway->startRecording($bridgeId, $name, $this->limits->maxCallSeconds($team->application->companyId));

            try {
                $this->gateway->announceRecording($bridgeId);
            } catch (Throwable $failure) {
                $this->stopQuietly($name);

                throw $failure;
            }

            return $recording;
        });
    }

    private function stopQuietly(string $name): void
    {
        try {
            $this->gateway->stopRecording($name);
            $this->gateway->deleteRecording($name);
        } catch (ExternalServiceUnavailable) {
            // The announcement failure is the one to report; the sweep of the voice server's recording folder removes what is left.
        }
    }
}
