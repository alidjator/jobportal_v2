<?php

namespace Src\Shared\Application\Organization;

/** Which ERP group a tenant belongs to, for the contexts that tag their records with it (the audit log), and what a group is called. Provided by Organization. */
interface GroupDirectory
{
    /** The group of the company's legal entity; null for an external tenant (a company that is not linked to a legal entity) or an unknown company. */
    public function groupOfCompany(int $companyId): ?int;

    /** The name of the group, or null when there is no such group. */
    public function nameOfGroup(int $groupId): ?string;
}
