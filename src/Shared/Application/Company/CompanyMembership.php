<?php

namespace Src\Shared\Application\Company;

/** The company a member belongs to and their role there (`company_admin` or `recruiter`). */
final readonly class CompanyMembership
{
    public function __construct(
        public int $companyId,
        public string $role,
    ) {}
}
