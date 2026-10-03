<?php

namespace Src\Recruitment\Application\JobManagement\Commands;

use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\Ports\PostingSettings;
use Src\Recruitment\Application\JobManagement\Ports\Transactions;
use Src\Recruitment\Application\JobManagement\ResolvesCompany;
use Src\Recruitment\Domain\JobManagement\CompanyPlanRepository;
use Src\Recruitment\Domain\JobManagement\JobMessages;
use Src\Recruitment\Domain\JobManagement\JobRepository;
use Src\Recruitment\Domain\JobManagement\PromotionBadge;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Promosikan lowongan (featured/highlight) memakai kuota paket perusahaan. */
final class PromoteJob
{
    use ResolvesCompany;

    public function __construct(
        CompanyDirectory $companies,
        private readonly JobRepository $jobs,
        private readonly CompanyPlanRepository $plans,
        private readonly PostingSettings $settings,
        private readonly Transactions $transactions,
    ) {
        $this->companies = $companies;
    }

    public function __invoke(int $userId, string $slug, PromotionBadge $badge): string
    {
        $companyId = $this->companyId($userId);
        $job = $this->jobs->findOwnedBySlug($companyId, $slug)
            ?? throw new EntityNotFound(JobMessages::get('job_not_found'));

        $this->transactions->run(function () use ($companyId, $badge, $job) {
            $plan = $this->plans->forCompany($companyId)
                ?? throw new BusinessRuleViolation(JobMessages::get('you_are_not_authorized_to_perform_this_action'));

            $plan->consumeBadge($badge);
            $this->plans->save($plan);

            if ($badge === PromotionBadge::Featured) {
                $days = $this->settings->featuredJobDays();
                $this->jobs->update($job['id'], [
                    'featured' => 1,
                    'highlight' => 0,
                    'featured_until' => $days > 0 ? now()->addDays($days)->format('Y-m-d') : null,
                    'highlight_until' => null,
                ]);
            } else {
                $days = $this->settings->highlightJobDays();
                $this->jobs->update($job['id'], [
                    'featured' => 0,
                    'highlight' => 1,
                    'highlight_until' => $days > 0 ? now()->addDays($days)->format('Y-m-d') : null,
                    'featured_until' => null,
                ]);
            }
        });

        return JobMessages::get('job_promote_successfully');
    }
}
