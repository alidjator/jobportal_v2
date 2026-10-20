<?php

namespace Src\JobSeeker\Application\ImportSummary;

use Src\JobSeeker\Application\JobSeekerGuard;
use Src\JobSeeker\Domain\ImportSummary;
use Src\JobSeeker\Domain\ImportSummaryRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The import summary of the caller. */
final readonly class GetMyImportSummary
{
    public function __construct(private JobSeekerGuard $guard, private ImportSummaryRepository $summaries) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound `no-import-summary` when the account has none, or it was purged
     */
    public function execute(Actor $actor): ImportSummary
    {
        return $this->summaries->find($this->guard->userId($actor)) ?? throw new EntityNotFound('There is no import summary for this account.', 'no-import-summary');
    }
}
