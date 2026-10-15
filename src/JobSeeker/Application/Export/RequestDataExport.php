<?php

namespace Src\JobSeeker\Application\Export;

use Src\JobSeeker\Application\JobSeekerGuard;
use Src\JobSeeker\Domain\DataExport;
use Src\JobSeeker\Domain\DataExportRepository;
use Src\JobSeeker\Domain\DataExportStatus;
use Src\JobSeeker\Domain\Events\DataExportRequested;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * A job seeker asks for a copy of their personal data. One at a time and at most one in 24 hours (a failed one does not count); the archive is built in the
 * background. The profile row is locked, so two simultaneous requests cannot both pass the checks.
 */
final readonly class RequestDataExport
{
    public function __construct(
        private JobSeekerGuard $guard,
        private ProfileRepository $profiles,
        private DataExportRepository $exports,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws StateConflict `data-export-running`
     * @throws TooManyAttempts `data-export-limit`
     */
    public function execute(Actor $actor): DataExport
    {
        $userId = $this->guard->userId($actor);
        $now = $this->clock->now();

        $export = $this->transactions->run(function () use ($userId, $now): DataExport {
            $this->profiles->lock($userId, $now);
            $latest = $this->exports->latestOf($userId);

            if ($latest !== null && $latest->isRunning($now)) {
                throw new StateConflict('A copy of your data is already being prepared.', 'data-export-running');
            }

            if ($latest !== null && $latest->status !== DataExportStatus::Failed) {
                $nextAt = $latest->createdAt->modify('+'.DataExport::REQUEST_INTERVAL_HOURS.' hours');

                if ($nextAt > $now) {
                    throw new TooManyAttempts('You can ask for a copy of your data once a day.', max(1, $nextAt->getTimestamp() - $now->getTimestamp()), 'data-export-limit');
                }
            }

            return $this->exports->start($userId, $now);
        });

        $this->events->dispatch([new DataExportRequested($export->id, $userId, $now)]);

        return $export;
    }
}
