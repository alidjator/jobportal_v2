<?php

namespace Src\Job\Application;

/** The company member who acts on jobs: their account, their company and whether they are a company admin (otherwise a recruiter). */
final readonly class JobMember
{
    public function __construct(
        public int $userId,
        public int $companyId,
        public bool $isCompanyAdmin,
    ) {}
}
