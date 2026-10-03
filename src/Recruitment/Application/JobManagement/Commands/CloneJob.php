<?php

namespace Src\Recruitment\Application\JobManagement\Commands;

use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\Ports\Transactions;
use Src\Recruitment\Application\JobManagement\ResolvesCompany;
use Src\Recruitment\Domain\JobManagement\CompanyPlanRepository;
use Src\Recruitment\Domain\JobManagement\JobMessages;
use Src\Recruitment\Domain\JobManagement\JobRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Duplikasi lowongan; memakai 1 kuota lowongan (+ kuota badge bila badge asli ingin dipertahankan). */
final class CloneJob
{
    use ResolvesCompany;

    public function __construct(
        CompanyDirectory $companies,
        private readonly JobRepository $jobs,
        private readonly CompanyPlanRepository $plans,
        private readonly Transactions $transactions,
    ) {
        $this->companies = $companies;
    }

    /** @return array{data: array<string, mixed>, message: string} */
    public function __invoke(int $userId, string $slug): array
    {
        $companyId = $this->companyId($userId);
        $job = $this->jobs->findOwnedBySlug($companyId, $slug)
            ?? throw new EntityNotFound(JobMessages::get('job_not_found'));

        $clone = $this->transactions->run(function () use ($companyId, $job) {
            $plan = $this->plans->forCompany($companyId)
                ?? throw new BusinessRuleViolation(JobMessages::get('you_have_reached_your_plan_limit_please_upgrade_your_plan'));

            $plan->consumeJob();
            [$featured, $highlight] = $plan->inheritBadges((bool) $job['featured'], (bool) $job['highlight']);

            $clone = $this->jobs->replicate($job['id'], [
                'featured' => (int) $featured,
                'highlight' => (int) $highlight,
                'featured_until' => $featured ? $job['featured_until'] : null,
                'highlight_until' => $highlight ? $job['highlight_until'] : null,
                'job_order_id' => null,
                'parent_job_id' => null,
                'waiting_for_edit_approval' => 0,
            ]);
            $this->plans->save($plan);

            return $clone;
        });

        return ['data' => $clone, 'message' => JobMessages::get('job_cloned_successfully')];
    }
}
