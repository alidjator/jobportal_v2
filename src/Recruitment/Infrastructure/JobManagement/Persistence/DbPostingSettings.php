<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Application\JobManagement\Ports\PostingSettings;

/** Baris pertama tabel `settings` (padanan `setting()` lama), dengan default skema bila kosong. */
class DbPostingSettings implements PostingSettings
{
    public function jobAutoApproved(): bool
    {
        return (bool) $this->value('job_auto_approved', 0);
    }

    public function editedJobAutoApproved(): bool
    {
        return (bool) $this->value('edited_job_auto_approved', 1);
    }

    public function deadlineExpirationLimitDays(): int
    {
        return (int) $this->value('job_deadline_expiration_limit', 30);
    }

    public function featuredJobDays(): int
    {
        return (int) $this->value('featured_job_days', 0);
    }

    public function highlightJobDays(): int
    {
        return (int) $this->value('highlight_job_days', 0);
    }

    public function appCountryName(): string
    {
        $countryId = $this->value('app_country', null);

        return $countryId === null ? '' : (string) DB::table('countries')->where('id', $countryId)->value('name');
    }

    private function value(string $column, mixed $default): mixed
    {
        // Tidak di-cache: baris settings kecil dan bisa diubah admin kapan saja.
        return DB::table('settings')->orderBy('id')->value($column) ?? $default;
    }
}
