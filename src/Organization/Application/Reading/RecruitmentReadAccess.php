<?php

namespace Src\Organization\Application\Reading;

use Src\Organization\Application\Group\GroupAccess;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyMembership;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The one read rule the recruitment contexts gain for the ERP: a group member with `group.recruitment.view`, in a group that is not suspended, may read the jobs,
 * applicants and reports of every tenant of their group. The member gets a read-only membership of that tenant (reading applicants, every job, analytics, nothing else), so the
 * recruitment use cases answer as they do for a recruiter and every action that changes something is refused by their own permission checks. A tenant of another group, or an
 * external tenant, does not exist for the member.
 */
final readonly class RecruitmentReadAccess
{
    /** @var list<string> */
    private const array READ_PERMISSIONS = [CompanyPermission::ApplicationsView->value, CompanyPermission::JobsViewAll->value, CompanyPermission::AnalyticsView->value];

    public function __construct(private GroupAccess $access, private TenantRegistry $tenants, private LegalEntityRepository $entities) {}

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the company is not a tenant of the member's group
     */
    public function of(int $userId, int $companyId): TenantRead
    {
        $context = $this->access->context(Actor::companyMember($userId), GroupPermission::RecruitmentView);
        $entityId = $this->tenants->legalEntityOf($companyId);

        if ($entityId === null || $this->entities->find($entityId)?->groupId !== $context->group->id) {
            throw EntityNotFound::of('Company', $companyId);
        }

        return new TenantRead((int) $context->group->id, $companyId, new CompanyMembership($companyId, CompanyMembership::ROLE_MEMBER, self::READ_PERMISSIONS));
    }
}
