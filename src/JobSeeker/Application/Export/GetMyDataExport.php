<?php

namespace Src\JobSeeker\Application\Export;

use Src\JobSeeker\Application\JobSeekerGuard;
use Src\JobSeeker\Domain\DataExport;
use Src\JobSeeker\Domain\DataExportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The caller's latest request for a copy of their data. */
final readonly class GetMyDataExport
{
    public function __construct(private JobSeekerGuard $guard, private DataExportRepository $exports) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound when no copy was ever asked for
     */
    public function execute(Actor $actor): DataExport
    {
        return $this->exports->latestOf($this->guard->userId($actor)) ?? throw EntityNotFound::of('DataExport', $this->guard->userId($actor));
    }
}
