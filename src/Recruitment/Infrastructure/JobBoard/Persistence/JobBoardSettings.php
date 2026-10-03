<?php

namespace Src\Recruitment\Infrastructure\JobBoard\Persistence;

use Illuminate\Support\Facades\DB;

/**
 * Pembacaan read-only baris pertama tabel `settings` (milik back office/Content)
 * untuk pengaturan yang memengaruhi tampilan lowongan: mode negara dan masa promosi.
 */
final class JobBoardSettings
{
    private ?object $row = null;

    private bool $loaded = false;

    public function featuredDays(): int
    {
        return (int) ($this->row()->featured_job_days ?? 0);
    }

    public function highlightDays(): int
    {
        return (int) ($this->row()->highlight_job_days ?? 0);
    }

    /** Nama negara tunggal bila `app_country_type = single_base`, selain itu null. */
    public function singleBaseCountryName(): ?string
    {
        $row = $this->row();

        if (($row->app_country_type ?? null) !== 'single_base' || ! $row->app_country) {
            return null;
        }

        return DB::table('countries')->where('id', $row->app_country)->value('name');
    }

    private function row(): ?object
    {
        if (! $this->loaded) {
            $this->row = DB::table('settings')->first();
            $this->loaded = true;
        }

        return $this->row;
    }
}
