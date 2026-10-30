<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\BlockedCompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** A job seeker lifts a block. Lifting one that is not there changes nothing. */
final readonly class UnblockCompany
{
    public function __construct(private JobSeekerGuard $guard, private BlockedCompanyRepository $blocked) {}

    /** @throws AccessDenied `job-seeker-only` */
    public function execute(Actor $actor, int $companyId): void
    {
        $this->blocked->remove($this->guard->userId($actor), $companyId);
    }
}
