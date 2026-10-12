<?php

namespace Src\Recruitment\Application\Voice;

use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Recruitment\Domain\Voice\CallRepository;
use Src\Recruitment\Domain\Voice\InterviewCall;
use Src\Recruitment\Domain\Voice\VoiceSessionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member of the panel dials the candidate. The team must already be in the bridge, the interview must not have a call in progress, the dial attempts of the
 * interview, the call minutes of the company in the month and the calls in progress on the platform must all have room. The call is stored before it is
 * dialled; when the voice server refuses the dial request the row is removed so the attempt is not used up.
 */
final readonly class PlaceInterviewCall
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private VoiceSessions $sessions,
        private VoiceSessionRepository $voice,
        private CallRepository $calls,
        private VoiceLimits $limits,
        private VoiceGateway $gateway,
        private Settings $settings,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-interview-participant`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `not-a-voice-interview`, `interview-not-confirmed`, `voice-disabled`, `no-phone-number`, `voice-window-closed`, `team-not-connected`, `call-in-progress`, `call-attempts-used`, `voice-quota-used` or `voice-capacity-full`
     * @throws ExternalServiceUnavailable
     */
    public function execute(Actor $actor, int $applicationId): InterviewCall
    {
        $call = $this->transactions->run(function () use ($actor, $applicationId): InterviewCall {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true);
            $companyId = $team->application->companyId;
            $interview = $this->sessions->callable($team->application);
            $interviewId = (int) $interview->id;

            if (! $this->sessions->isOnPanel($interview, $team->member->userId)) {
                throw new AccessDenied('You are not on the panel of this interview.', 'not-an-interview-participant');
            }

            if (! $this->voice->isOpen($interviewId) || $this->gateway->channelsInBridge(VoiceNames::bridge($interviewId)) < 1) {
                throw new StateConflict('Join the call from your browser before you dial the candidate.', 'team-not-connected');
            }

            if ($this->calls->liveOf($interviewId) !== null) {
                throw new StateConflict('The candidate is already being called.', 'call-in-progress');
            }

            $attempts = $this->settings->integer(InterviewSettings::VOICE_MAX_ATTEMPTS, $companyId);

            if ($this->calls->countOf($interviewId) >= $attempts) {
                throw new StateConflict("The candidate was already called {$attempts} times. Reschedule the interview.", 'call-attempts-used');
            }

            if ($this->limits->remainingSeconds($companyId, $this->clock->now()) <= 0) {
                throw new StateConflict('Your company used all its phone interview minutes of this month.', 'voice-quota-used');
            }

            $this->calls->lockCapacity();

            if ($this->calls->liveCount() >= $this->settings->integer(InterviewSettings::VOICE_MAX_CONCURRENT_CALLS, $companyId)) {
                throw new StateConflict('Too many phone interviews are running right now. Try again in a few minutes.', 'voice-capacity-full');
            }

            return $this->calls->add(InterviewCall::dial($interviewId, $team->member->userId, (string) $interview->candidatePhone, $this->clock->now()));
        });

        try {
            $this->gateway->dial($call->channelId, $call->number, (string) $call->id);
        } catch (ExternalServiceUnavailable $failure) {
            $this->transactions->run(fn () => $this->calls->delete((int) $call->id));

            throw $failure;
        }

        return $call;
    }
}
