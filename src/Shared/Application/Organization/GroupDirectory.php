<?php

namespace Src\Shared\Application\Organization;

/** Which ERP group a tenant belongs to, for the contexts that tag their records with it (the audit log). Provided by Organization. */
interface GroupDirectory
{
    /** The group of the company's legal entity; null for an external tenant (a company that is not linked to a legal entity) or an unknown company. */
    public function groupOfCompany(int $companyId): ?int;
}
