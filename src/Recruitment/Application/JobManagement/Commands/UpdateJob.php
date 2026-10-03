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
use Src\Recruitment\Domain\JobManagement\JobMessages;
use Src\Recruitment\Domain\JobManagement\JobRepository;
use Src\Recruitment\Domain\JobManagement\JobStatus;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Ubah lowongan milik perusahaan. Seperti alur web lama: bila `edited_job_auto_approved` aktif
 * (atau lowongan masih pending) lowongan diubah di tempat; selain itu dibuat/diperbarui salinan
 * hasil edit (`parent_job_id`, `waiting_for_edit_approval`) yang menunggu persetujuan admin.
 */
final class UpdateJob
{
    use ResolvesCompany;

    public function __construct(
        CompanyDirectory $companies,
        private readonly JobRepository $jobs,
        private readonly PostingSettings $settings,
        private readonly LocationDirectory $locations,
        private readonly Transactions $transactions,
        private readonly JobNotifier $notifier,
        private readonly JobIndexer $indexer,
    ) {
        $this->companies = $companies;
    }

    /** @return array{job: array<string, mixed>, message: string} */
    public function __invoke(int $userId, string $slug, JobPosting $posting): array
    {
        $companyId = $this->companyId($userId);
        $job = $this->jobs->findOwnedBySlug($companyId, $slug)
            ?? throw new EntityNotFound(JobMessages::get('job_not_found'));

        $country = Str::title($this->settings->appCountryName());
        $city = Str::title($this->locations->cityName($posting->cityBps));
        $subdistrict = Str::title($this->locations->subdistrictName($posting->subdistrictBps));
        $columns = $posting->toColumns($country, "{$subdistrict}, {$city}");

        $inPlace = $this->settings->editedJobAutoApproved() || $job['status'] === JobStatus::Pending->value;

        $main = $this->transactions->run(function () use ($inPlace, $companyId, $job, $columns, $posting) {
            if ($inPlace) {
                $main = $this->jobs->update($job['id'], $columns + ['status' => $this->statusAfterEdit($job, $posting)]);
            } else {
                $editState = [
                    'waiting_for_edit_approval' => 1,
                    'status' => JobStatus::Pending->value,
                ];
                $copy = $this->jobs->findPendingEdit($companyId, $job['id']);
                $main = $copy
                    ? $this->jobs->update($copy['id'], $columns + $editState)
                    : $this->jobs->replicate($job['id'], $columns + $editState + [
                        'company_id' => $companyId,
                        'parent_job_id' => $job['id'],
                        'featured' => 0,
                        'highlight' => 0,
                    ]);
            }

            $this->jobs->attachTags($main['id'], $posting->tags, replace: true);

            return $main;
        });

        if ($this->settings->editedJobAutoApproved() || ! $main['waiting_for_edit_approval']) {
            $message = JobMessages::get('job_updated_successfully');
        } else {
            $this->notifier->jobEditWaitingApproval($userId, $main);
            $message = JobMessages::get('your_job_successfully_updated_please_wait_for_approve_changes');
        }

        if ($main['status'] === JobStatus::Active->value) {
            $this->indexer->jobPublished($main);
        }

        return ['job' => $main, 'message' => $message];
    }

    /**
     * Edit di tempat menghitung ulang status dari deadline. Lowongan yang masih menunggu
     * persetujuan admin tidak boleh menjadi aktif lewat edit (kecuali auto-approve).
     *
     * @param  array<string, mixed>  $job
     */
    private function statusAfterEdit(array $job, JobPosting $posting): string
    {
        if ($job['status'] === JobStatus::Pending->value && ! $this->settings->jobAutoApproved()) {
            return JobStatus::Pending->value;
        }

        return JobStatus::forDeadline($posting->deadline >= now()->toDateString())->value;
    }
}
