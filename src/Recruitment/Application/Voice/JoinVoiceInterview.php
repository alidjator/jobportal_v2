<?php

namespace Src\Recruitment\Application\Voice;

use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A member of the panel gets a browser account to join the voice bridge of the interview. The candidate does not join: they are called. */
final readonly class JoinVoiceInterview
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private VoiceSessions $sessions,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-interview-participant`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview`, `not-a-voice-interview`, `interview-not-confirmed`, `voice-disabled`, `no-phone-number` or `voice-window-closed`
     * @throws ExternalServiceUnavailable
     */
    public function execute(Actor $actor, int $applicationId): VoiceCredentials
    {
        return $this->transactions->run(function () use ($actor, $applicationId): VoiceCredentials {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage, panelOpens: true);
            $interview = $this->sessions->callable($team->application);

            if (! $this->sessions->isOnPanel($interview, $team->member->userId)) {
                throw new AccessDenied('You are not on the panel of this interview.', 'not-an-interview-participant');
            }

            return $this->sessions->credentials($interview, $team->member->userId);
        });
    }
}
