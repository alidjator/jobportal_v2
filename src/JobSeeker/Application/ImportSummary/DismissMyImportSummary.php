<?php

namespace Src\JobSeeker\Application\ImportSummary;

use Src\JobSeeker\Application\JobSeekerGuard;
use Src\JobSeeker\Domain\ImportSummaryRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Closes the card of the import summary; the full list stays readable until the summary is purged. */
final readonly class DismissMyImportSummary
{
    public function __construct(private JobSeekerGuard $guard, private ImportSummaryRepository $summaries, private Clock $clock) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound `no-import-summary`
     */
    public function execute(Actor $actor): void
    {
        if (! $this->summaries->dismiss($this->guard->userId($actor), $this->clock->now())) {
            throw new EntityNotFound('There is no import summary for this account.', 'no-import-summary');
        }
    }
}
