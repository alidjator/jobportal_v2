<?php

namespace Src\Recruitment\Application\JobManagement\Commands;

use Illuminate\Support\Str;
use Src\Recruitment\Application\JobManagement\JobPosting;
use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Application\JobManagement\Ports\JobIndexer;
use Src\Recruitment\Application\JobManagement\Ports\JobNotifier;
use Src\Recruitment\Application\JobManagement\Ports\LocationDirectory;
use Src\Recruitment\Application\JobManagement\Ports\PostingSettings;
use Src\Recruitment\Application\JobManagement\Ports\Transactions;
use Src\Recruitment\Application\JobManagement\ResolvesCompany;
use Src\Recruitment\Domain\JobManagement\CompanyPlanRepository;
use Src\Recruitment\Domain\JobManagement\JobMessages;
use Src\Recruitment\Domain\JobManagement\JobRepository;
use Src\Recruitment\Domain\JobManagement\JobStatus;
use Src\Recruitment\Domain\JobManagement\PromotionBadge;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Buat lowongan baru milik perusahaan user. */
final class StoreJob
{
    use ResolvesCompany;

    public function __construct(
        CompanyDirectory $companies,
        private readonly JobRepository $jobs,
        private readonly CompanyPlanRepository $plans,
        private readonly PostingSettings $settings,
        private readonly LocationDirectory $locations,
        private readonly Transactions $transactions,
        private readonly JobNotifier $notifier,
        private readonly JobIndexer $indexer,
    ) {
        $this->companies = $companies;
    }

    /** @return array{job: array<string, mixed>, message: string} */
    public function __invoke(int $userId, JobPosting $posting): array
    {
        $companyId = $this->companyId($userId);

        $country = Str::title($this->settings->appCountryName());
        $city = Str::title($this->locations->cityName($posting->cityBps));
        $subdistrict = Str::title($this->locations->subdistrictName($posting->subdistrictBps));

        $attributes = $posting->toColumns($country, "{$subdistrict}, {$city}") + [
            'company_id' => $companyId,
            'status' => ($this->settings->jobAutoApproved() ? JobStatus::Active : JobStatus::Pending)->value,
            'featured' => 0,
            'highlight' => 0,
        ];

        $job = $this->transactions->run(function () use ($companyId, $posting, $attributes) {
            if ($posting->badge !== null) {
                $attributes = $this->withBadge($companyId, $posting->badge, $attributes);
            }

            $job = $this->jobs->create($attributes);
            $this->jobs->attachTags($job['id'], $posting->tags, replace: false);

            return $job;
        });

        $this->notifier->jobCreated($userId, $job);

        if ($job['status'] === JobStatus::Active->value) {
            $this->indexer->jobPublished($job);
        }

        return ['job' => $job, 'message' => JobMessages::get('job_created_successfully')];
    }

    /**
     * Badge featured/highlight memakai kuota paket (seperti promote); aplikasi lama menyetel flag tanpa cek kuota.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withBadge(int $companyId, PromotionBadge $badge, array $attributes): array
    {
        $plan = $this->plans->forCompany($companyId)
            ?? throw new BusinessRuleViolation(JobMessages::get('you_are_not_authorized_to_perform_this_action'));

        $plan->consumeBadge($badge);
        $this->plans->save($plan);

        if ($badge === PromotionBadge::Featured) {
            $days = $this->settings->featuredJobDays();
            $attributes['featured'] = 1;
            $attributes['featured_until'] = $days > 0 ? now()->addDays($days)->format('Y-m-d') : null;
        } else {
            $days = $this->settings->highlightJobDays();
            $attributes['highlight'] = 1;
            $attributes['highlight_until'] = $days > 0 ? now()->addDays($days)->format('Y-m-d') : null;
        }

        return $attributes;
    }
}
