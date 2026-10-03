<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Infrastructure\Persistence\Eloquent\CompanyRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\ContactInfoRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\CountryRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\IndustryTypeRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\JobRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\OrganizationTypeRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\SocialLinkRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\TeamSizeRecord;
use Src\Company\Infrastructure\Persistence\Eloquent\UserRecord;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

class EloquentCompanyRepository implements CompanyRepository
{
    private const LOCATION_COLUMNS = [
        'address', 'neighborhood', 'locality', 'place', 'district', 'subdistrict',
        'kelurahan', 'postcode', 'region', 'country', 'long', 'lat',
    ];

    public function idForUser(int $userId): int
    {
        $id = DB::table('companies')->where('user_id', $userId)->value('id');

        return $id !== null ? (int) $id : throw new EntityNotFound('Company not found');
    }

    public function profile(int $userId): array
    {
        $company = CompanyRecord::query()->with(['user.socialInfo', 'user.contactInfo', 'userPlan'])->where('user_id', $userId)->first();

        return $company?->toProfileResource() ?? [];
    }

    public function dashboard(int $companyId): array
    {
        $recent = JobRecord::query()
            ->select(['id', 'title', 'company_id', 'country', 'max_salary', 'min_salary', 'job_type_id', 'slug', 'deadline', 'status'])
            ->where('company_id', $companyId)
            ->latest()->latest('id')
            ->with('company:id', 'job_type:id')
            ->withCount('appliedJobs')
            ->take(4)->get();

        return [
            'openJobCount' => JobRecord::query()->where('company_id', $companyId)->where('status', 'active')->count(),
            'recentJobs' => $recent->toArray(),
            // Tabel `bookmark_company` (bookmark kandidat) dimiliki context ini.
            'savedCandidateCount' => DB::table('bookmark_company')->where('company_id', $companyId)->count(),
        ];
    }

    public function accountProgress(int $userId): array
    {
        $user = UserRecord::query()->with('company', 'contactInfo', 'socialInfo')->findOrFail($userId);

        return [
            'user' => $user->toArray(),
            'industry_types' => IndustryTypeRecord::query()->get()->toArray(),
            'organization_types' => OrganizationTypeRecord::query()->get()->toArray(),
            'team_sizes' => TeamSizeRecord::query()->get()->toArray(),
            'socials' => $user->socialInfo->toArray(),
            // Tabel `countries` (ReferenceData), read-only: pilihan negara pada form profil.
            'countries' => CountryRecord::query()->get()->toArray(),
        ];
    }

    public function socialLinks(int $userId): array
    {
        return SocialLinkRecord::query()->where('user_id', $userId)->orderBy('id')->get()
            ->map(fn ($item) => ['id' => $item->id, 'social_media' => $item->social_media, 'url' => $item->url])
            ->all();
    }

    public function images(int $userId): array
    {
        $row = DB::table('companies')->where('user_id', $userId)->first(['logo', 'banner']);

        return ['logo' => $row->logo ?? null, 'banner' => $row->banner ?? null];
    }

    public function saveBasics(int $userId, ?string $name, string $bio, ?string $logo, ?string $banner): void
    {
        DB::transaction(function () use ($userId, $name, $bio, $logo, $banner) {
            if ($name !== null) {
                DB::table('users')->where('id', $userId)->update(['name' => $name, 'updated_at' => now()]);
            }

            $values = ['bio' => $bio, 'updated_at' => now()];
            if ($logo !== null) {
                $values['logo'] = $logo;
            }
            if ($banner !== null) {
                $values['banner'] = $banner;
            }

            DB::table('companies')->where('user_id', $userId)->update($values);
        });
    }

    public function saveFounding(int $userId, array $data): void
    {
        DB::transaction(function () use ($userId, $data) {
            $organization = $this->resolveType('organization_types', 'organization_type_translations', 'organization_type_id', $data['organization_type']);
            $industry = $this->resolveType('industry_types', 'industry_type_translations', 'industry_type_id', $data['industry_type']);

            DB::table('companies')->where('user_id', $userId)->update([
                'organization_type_id' => $organization,
                'industry_type_id' => $industry,
                'establishment_date' => $data['establishment_date'] ? date('Y-m-d', strtotime($data['establishment_date'])) : null,
                'team_size_id' => $data['team_size_id'],
                'website' => $data['website'],
                'vision' => $data['vision'],
                'updated_at' => now(),
            ]);
        });
    }

    public function replaceSocialLinks(int $userId, array $links): void
    {
        DB::transaction(function () use ($userId, $links) {
            SocialLinkRecord::query()->where('user_id', $userId)->delete();

            foreach ($links as $link) {
                SocialLinkRecord::query()->create(['user_id' => $userId, 'social_media' => $link['social_media'], 'url' => $link['url']]);
            }
        });
    }

    public function saveContact(int $userId, array $contact, array $location): void
    {
        DB::transaction(function () use ($userId, $contact, $location) {
            ContactInfoRecord::query()->updateOrCreate(['user_id' => $userId], $contact);

            $values = array_intersect_key($location, array_flip(self::LOCATION_COLUMNS));
            CompanyRecord::query()->where('user_id', $userId)->update($values + ['profile_completion' => 1]);
        });
    }

    /** Cari tipe (organisasi/industri) berdasarkan id atau nama; nama baru dibuat untuk semua bahasa seperti aplikasi lama. */
    private function resolveType(string $table, string $translationTable, string $foreignKey, string $value): int
    {
        if (ctype_digit($value)) {
            $id = DB::table($table)->where('id', (int) $value)->value('id');

            return $id !== null ? (int) $id : throw new BusinessRuleViolation('Invalid '.str_replace('_', ' ', rtrim($table, 's')));
        }

        $id = DB::table($translationTable)->where('name', $value)->value($foreignKey);
        if ($id !== null) {
            return (int) $id;
        }

        $id = (int) DB::table($table)->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $locales = DB::table('languages')->pluck('code')->all() ?: [app()->getLocale()];

        foreach ($locales as $locale) {
            DB::table($translationTable)->insert([
                $foreignKey => $id, 'name' => $value, 'locale' => $locale, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }
}
