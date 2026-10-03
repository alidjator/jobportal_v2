<?php

namespace Src\Content\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Content\Domain\AboutRepository;
use Src\Content\Infrastructure\Persistence\Eloquent\Testimonial;

/**
 * Statistik membaca read-only tabel milik context lain: `jobs`, `companies`, `candidates`,
 * `countries`, `settings` (hanya untuk angka ringkasan halaman About).
 */
final class EloquentAboutRepository implements AboutRepository
{
    public function testimonials(): array
    {
        return Testimonial::all()->toArray();
    }

    public function liveJobs(?int $countryId): int
    {
        $jobs = DB::table('jobs')
            ->where('waiting_for_edit_approval', false)
            ->where('status', 'active')
            ->where('deadline', '>=', now()->toDateString());

        $countryName = $countryId ? DB::table('countries')->where('id', $countryId)->value('name') : null;

        if ($countryName === null) {
            $setting = DB::table('settings')->first(['app_country_type', 'app_country']);
            if ($setting && $setting->app_country_type === 'single_base' && $setting->app_country) {
                $countryName = DB::table('countries')->where('id', $setting->app_country)->value('name');
            }
        }

        if ($countryName !== null) {
            $jobs->where('country', 'like', "%{$countryName}%");
        }

        return $jobs->count();
    }

    public function companyCount(): int
    {
        return DB::table('companies')->count();
    }

    public function candidateCount(): int
    {
        return DB::table('candidates')->count();
    }
}
