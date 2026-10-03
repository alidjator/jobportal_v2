<?php

namespace Src\Recruitment\Interface\Http\Requests\JobManagement;

use Illuminate\Validation\Rule;
use Src\Recruitment\Application\JobManagement\Ports\CompanyDirectory;
use Src\Recruitment\Domain\JobManagement\JobRepository;

/** Update memakai aturan yang sama dengan store (kolom lengkap), `job_order_id` unik selain milik lowongan ini. */
class UpdateJobRequest extends StoreJobRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = app(CompanyDirectory::class)->idForUser((int) $this->user()?->getAuthIdentifier());
        $job = $companyId === null ? null : app(JobRepository::class)->findOwnedBySlug($companyId, (string) $this->route('slug'));

        return array_merge(parent::rules(), [
            'job_order_id' => ['nullable', 'integer', Rule::unique('jobs', 'job_order_id')->ignore($job['id'] ?? null)],
        ]);
    }
}
