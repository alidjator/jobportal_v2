<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The check every candidate action starts with: the caller is a member of a company that is not suspended, whatever their role. */
final readonly class CandidateAccess
{
    public function __construct(private CompanyDirectory $companies) {}

    /** @throws AccessDenied `not-a-company-member` or `company-suspended` */
    public function viewer(Actor $actor): CompanyViewer
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can search candidates.', 'not-a-company-member');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return new CompanyViewer($actor->id, $actor->membership->companyId, $actor->isCompanyAdmin());
    }
}
