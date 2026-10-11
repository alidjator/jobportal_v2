<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

interface CompanyBatteryRepository
{
    /** Lets the company use the battery; granting twice changes nothing. */
    public function grant(int $companyId, int $testId, int $adminId, DateTimeImmutable $now): void;

    public function revoke(int $companyId, int $testId): void;

    /**
     * The batteries granted to a company.
     *
     * @return list<Test>
     */
    public function grantedTo(int $companyId): array;
}
