<?php

namespace Src\Recruitment\Application\Voice;

use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\Voice\CallRepository;
use Src\Recruitment\Domain\Voice\InterviewCall;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member of the panel ends the call in progress. The voice server confirms by its hangup event, which closes the call in the log; this only asks it to hang up.
 * The panel is checked against the interview as at the time of the request, so a former member cannot cut a call.
 */
final readonly class HangUpInterviewCall
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private InterviewRepository $interviews,
        private VoiceSessions $sessions,
        private CallRepository $calls,
        private VoiceGateway $gateway,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-interview-participant`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview` or `no-live-call`
     * @throws ExternalServiceUnavailable
     */
    public function execute(Actor $actor, int $applicationId): InterviewCall
    {
        $team = $this->lookup->find($actor, $applicationId);
        $interview = $this->interviews->active((int) $team->application->id) ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');

        if (! $this->sessions->isOnPanel($interview, $team->member->userId)) {
            throw new AccessDenied('You are not on the panel of this interview.', 'not-an-interview-participant');
        }

        $call = $this->calls->liveOf((int) $interview->id) ?? throw new StateConflict('There is no call in progress.', 'no-live-call');
        $this->gateway->hangup($call->channelId);

        return $call;
    }
}
