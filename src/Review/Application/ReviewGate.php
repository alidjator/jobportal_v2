<?php

namespace Src\Review\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Company\PublicCompanyCard;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The checks the review actions start with: who the actor is, and which company a request is about. */
final readonly class ReviewGate
{
    public function __construct(
        private PublicCompanies $publicCompanies,
        private CompanyDirectory $companies,
    ) {}

    /**
     * The company behind a public page; a company the public cannot see does not exist here.
     *
     * @throws EntityNotFound
     */
    public function publicCompany(string $slug): PublicCompanyCard
    {
        return $this->publicCompanies->cardBySlug($slug) ?? throw EntityNotFound::of('Company', $slug);
    }

    /** @throws AccessDenied `job-seeker-only` */
    public function jobSeekerId(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can do this.', 'job-seeker-only');
        }

        return $actor->id;
    }

    /** @throws AccessDenied `admin-only` */
    public function adminId(Actor $actor): int
    {
        if ($actor->kind !== ActorKind::Admin || $actor->id === null) {
            throw new AccessDenied('Only an admin can do this.', 'admin-only');
        }

        return $actor->id;
    }

    /**
     * The company of a member of an active company, and for a reply or a report only when they are a company admin.
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     */
    public function memberCompanyId(Actor $actor, bool $adminRequired): int
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        if ($adminRequired && ! $actor->isCompanyAdmin()) {
            throw new AccessDenied('Only a company admin can do this.', 'company-admin-required');
        }

        return $actor->membership->companyId;
    }
}
