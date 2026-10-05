<?php

namespace Src\Company\Domain;

use Src\Shared\Application\Company\CompanyMembership;

interface CompanyMemberRepository
{
    /** The company and role of the account, or null when it belongs to none. */
    public function findByUserId(int $userId): ?CompanyMembership;

    /** Makes the account a member. An account can belong to only one company; the database enforces it. */
    public function add(int $companyId, int $userId, CompanyRole $role): void;

    /** The member of that company, or null when the account is not one of its members. */
    public function find(int $companyId, int $userId): ?CompanyMember;

    /** @return list<CompanyMember> every member of the company, in no particular order */
    public function ofCompany(int $companyId): array;

    /**
     * The account ids of the company's company admins, locked until the transaction ends, so two removals or demotions at the same time cannot
     * both see another admin and leave the company with none.
     *
     * @return list<int>
     */
    public function lockCompanyAdminIds(int $companyId): array;

    /** Releases the membership; the account is free to belong to a company again. */
    public function remove(int $companyId, int $userId): void;

    public function changeRole(int $companyId, int $userId, CompanyRole $role): void;
}
