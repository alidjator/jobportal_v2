<?php

namespace Src\Recruitment\Domain\JobManagement;

interface CompanyPlanRepository
{
    public function forCompany(int $companyId): ?PlanQuota;

    public function save(PlanQuota $quota): void;
}
