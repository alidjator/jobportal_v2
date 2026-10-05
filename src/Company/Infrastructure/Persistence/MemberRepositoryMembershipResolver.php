<?php

namespace Src\Company\Infrastructure\Persistence;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Shared\Application\Company\CompanyMembership;
use Src\Shared\Application\Company\CompanyMembershipResolver;

/** Company's answer to "which company does this account belong to?", read when an `Actor` is built. */
final readonly class MemberRepositoryMembershipResolver implements CompanyMembershipResolver
{
    public function __construct(private CompanyMemberRepository $members) {}

    public function forUser(int $userId): ?CompanyMembership
    {
        return $this->members->findByUserId($userId);
    }
}
