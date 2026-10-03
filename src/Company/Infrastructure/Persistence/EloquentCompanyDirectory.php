<?php

namespace Src\Company\Infrastructure\Persistence;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Src\Company\Domain\CompanyDirectory;
use Src\Company\Infrastructure\Persistence\Eloquent\CompanyRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\JobRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\UserRecord;

class EloquentCompanyDirectory implements CompanyDirectory
{
    private const RADIUS_KM = 50;

    public function paginate(array $filters, int $perPage, ?string $selectedCountry, ?int $viewerUserId): LengthAwarePaginator
    {
        $query = $this->activeCompanies()
            ->with('user:id,name,username')
            ->withCount(['jobs as activejobs' => function ($q) use ($selectedCountry) {
                $q->where('status', 'active');
                $this->applyHeaderFirstCountry($q, $selectedCountry);
            }]);

        if (filled($filters['keyword'] ?? null)) {
            $keyword = $filters['keyword'];
            $query->whereHas('user', fn ($q) => $q->where('name', 'LIKE', "%{$keyword}%"));
        }

        if (filled($filters['lat'] ?? null) && filled($filters['long'] ?? null)) {
            $lat = (float) $filters['lat'];
            $long = (float) $filters['long'];
            $query->where(function (Builder $q) use ($lat, $long, $filters) {
                $q->whereRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(`lat`)) * cos(radians(`long`) - radians(?)) + sin(radians(?)) * sin(radians(`lat`)))) <= ?',
                    [$lat, $long, $lat, self::RADIUS_KM],
                )->orWhere('country', $filters['location'] ?? '');
            });
        }

        // Nama tipe tersimpan di tabel `*_translations` (aplikasi lama mengira kolom `name` ada di tabel utama).
        if (filled($filters['industry_type'] ?? null)) {
            $query->whereIn('industry_type_id', DB::table('industry_type_translations')->where('name', $filters['industry_type'])->select('industry_type_id'));
        }

        if (filled($filters['organization_type'] ?? null)) {
            $query->whereIn('organization_type_id', DB::table('organization_type_translations')->where('name', $filters['organization_type'])->select('organization_type_id'));
        }

        if (filled($filters['location'] ?? null)) {
            $query->where('country', $filters['location']);
        }

        $query->orderBy('created_at', ($filters['sortBy'] ?? null) && $filters['sortBy'] !== 'latest' ? 'asc' : 'desc')
            ->orderByDesc('activejobs')
            ->orderByDesc('id');

        return $query->paginate($perPage)
            ->withQueryString()
            ->through(fn (CompanyRecord $company) => $company->toListItem());
    }

    public function detail(string $username, ?string $selectedCountry, ?int $viewerUserId): ?array
    {
        $user = UserRecord::query()->select(['id', 'name', 'username'])->with(['contactInfo', 'socialInfo'])->where('username', $username)->first();

        if ($user === null) {
            return null;
        }

        $company = $this->activeCompanies()
            ->with('organization', 'industry', 'team_size:id')
            ->where('user_id', $user->id)
            ->withCount(['jobs as activejobs' => function ($q) use ($selectedCountry) {
                $q->where('status', 'active')->where('deadline', '>=', Carbon::now()->toDateString());
                $this->applyHeaderFirstCountry($q, $selectedCountry);
            }])
            ->first();

        if ($company === null) {
            return null;
        }

        $company->setAttribute('candidatemarked', $viewerUserId !== null && DB::table('bookmark_candidate_company')
            ->join('candidates', 'candidates.id', '=', 'bookmark_candidate_company.candidate_id')
            ->where('bookmark_candidate_company.company_id', $company->id)
            ->where('candidates.user_id', $viewerUserId)
            ->exists());

        $jobs = JobRecord::query()
            ->with(['company.user:id,name,username,image', 'job_type:id'])
            ->where('company_id', $company->id)
            ->where('waiting_for_edit_approval', false)
            ->where('status', 'active')
            ->where('deadline', '>=', Carbon::now()->toDateString())
            ->latest()->latest('id');

        $setting = $this->settingCountry();
        if ($setting !== null) {
            $jobs->where('country', 'LIKE', "%{$setting}%");
        } elseif (($name = $this->countryName($selectedCountry)) !== null) {
            $jobs->where('country', 'LIKE', "%{$name}%");
        }

        return [
            'companyDetails' => $company->toArray(),
            'user' => $user->toArray(),
            'open_jobs' => $jobs->paginate(8)->withQueryString(),
        ];
    }

    /** @return Builder<CompanyRecord> */
    private function activeCompanies(): Builder
    {
        return CompanyRecord::query()
            ->where('visibility', 1)
            ->whereHas('user', fn ($q) => $q->where('status', 1));
    }

    /** Filter negara: pilihan klien dulu; bila tidak ada dan app `single_base`, pakai negara aplikasi. */
    private function applyHeaderFirstCountry($query, ?string $selectedCountry): void
    {
        $name = $this->countryName($selectedCountry) ?? $this->settingCountry();

        if ($name !== null) {
            $query->where('country', 'LIKE', "%{$name}%");
        }
    }

    /** Header `selected_country` boleh berisi nama negara (aplikasi lama) atau id negara; `all` = tanpa filter. */
    private function countryName(?string $selected): ?string
    {
        if ($selected === null || $selected === '' || $selected === 'all') {
            return null;
        }

        if (ctype_digit($selected)) {
            // Tabel `countries` (ReferenceData), read-only.
            return DB::table('countries')->where('id', (int) $selected)->value('name');
        }

        return $selected;
    }

    /** Nama negara aplikasi bila `settings.app_country_type = single_base`. */
    private function settingCountry(): ?string
    {
        // Tabel `settings` (dikelola admin) dan `countries`, read-only.
        $setting = DB::table('settings')->first(['app_country_type', 'app_country']);

        if ($setting === null || $setting->app_country_type !== 'single_base' || ! $setting->app_country) {
            return null;
        }

        return DB::table('countries')->where('id', $setting->app_country)->value('name');
    }
}
