<?php

namespace Src\Recruitment\Application\Voice;

use Src\Recruitment\Application\TeamApplicationLookup;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A member of the panel stops the running recording of the phone call. The file appears in the list when it has been fetched from the voice server. */
final readonly class StopVoiceRecording
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private InterviewRepository $interviews,
        private VoiceSessions $sessions,
        private VoiceRecordingStopper $stopper,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-interview-participant`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-interview` or `no-active-recording`
     * @throws ExternalServiceUnavailable
     */
    public function execute(Actor $actor, int $applicationId): InterviewRecording
    {
        $stopped = $this->transactions->run(function () use ($actor, $applicationId): InterviewRecording {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage, panelOpens: true);
            $interview = $this->interviews->active($applicationId, forUpdate: true) ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');

            if (! $this->sessions->isOnPanel($interview, $team->member->userId)) {
                throw new AccessDenied('You are not on the panel of this interview.', 'not-an-interview-participant');
            }

            return $this->stopper->stop((int) $interview->id) ?? throw new StateConflict('No recording is running.', 'no-active-recording');
        });

        $this->stopper->announce($stopped);

        return $stopped;
    }
}
