<?php

namespace Src\Company\Domain;

use Src\Shared\Application\Company\CompanyMembership;

interface CompanyMemberRepository
{
    /** The company, role and permissions of the account, or null when it belongs to none. */
    public function findByUserId(int $userId): ?CompanyMembership;

    /**
     * Makes the account a member. An account can belong to only one company; the database enforces it.
     *
     * @param  list<string>  $permissions  stored for a member, ignored for a company admin
     * @param  bool  $isOwner  the owner of the company; only for the first company admin, and only when the company has none yet
     */
    public function add(int $companyId, int $userId, CompanyRole $role, array $permissions, bool $isOwner = false): void;

    /** The member of that company, or null when the account is not one of its members. */
    public function find(int $companyId, int $userId): ?CompanyMember;

    /** @return list<CompanyMember> every member of the company, in no particular order */
    public function ofCompany(int $companyId): array;

    /**
     * The account ids of the company's active company admins (a suspended one does not count), locked until the transaction ends, so two removals,
     * demotions or suspensions at the same time cannot both see another admin and leave the company with none.
     *
     * @return list<int>
     */
    public function lockCompanyAdminIds(int $companyId): array;

    /** The account id of the company's owner, or null when it has none (the owner left, or the company has no company admin yet). */
    public function ownerUserId(int $companyId): ?int;

    /** Flags the member as the owner and clears the flag of the previous owner, in that order inside the caller's transaction. */
    public function setOwner(int $companyId, int $userId): void;

    public function suspend(int $companyId, int $userId, int $byUserId): void;

    public function reactivate(int $companyId, int $userId): void;

    /** Releases the membership; the account is free to belong to a company again. */
    public function remove(int $companyId, int $userId): void;

    public function changeRole(int $companyId, int $userId, CompanyRole $role): void;

    /**
     * Replaces the permissions of a member. A company admin has no rows, so a promotion passes an empty list.
     *
     * @param  list<string>  $permissions
     */
    public function replacePermissions(int $companyId, int $userId, array $permissions): void;
}
