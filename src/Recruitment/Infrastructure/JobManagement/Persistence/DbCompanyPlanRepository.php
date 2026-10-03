<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\JobManagement\CompanyPlanRepository;
use Src\Recruitment\Domain\JobManagement\PlanQuota;

/**
 * Kuota paket ada di tabel `user_plans` (milik context Billing). Karena promosi/duplikasi lowongan
 * harus memakai kuota secara atomik, kuota dibaca (lockForUpdate) dan dikurangi di sini; sebaiknya
 * dipindah ke kontrak Shared milik Billing.
 */
class DbCompanyPlanRepository implements CompanyPlanRepository
{
    public function forCompany(int $companyId): ?PlanQuota
    {
        $row = DB::table('user_plans')->where('company_id', $companyId)->orderBy('id')->lockForUpdate()->first();

        return $row === null
            ? null
            : new PlanQuota((int) $row->id, (int) $row->job_limit, (int) $row->featured_job_limit, (int) $row->highlight_job_limit);
    }

    public function save(PlanQuota $quota): void
    {
        DB::table('user_plans')->where('id', $quota->id)->update([
            'job_limit' => $quota->jobLimit(),
            'featured_job_limit' => $quota->featuredLimit(),
            'highlight_job_limit' => $quota->highlightLimit(),
            'updated_at' => now(),
        ]);
    }
}
