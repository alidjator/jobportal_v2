<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompanyRecord extends Model
{
    protected $table = 'companies';

    protected $guarded = [];

    protected $appends = ['logo_url', 'banner_url', 'full_address'];

    protected function casts(): array
    {
        return [
            'establishment_date' => 'datetime',
            'profile_completion' => 'boolean',
            'is_profile_verified' => 'boolean',
        ];
    }

    public function getFullAddressAttribute(): string
    {
        $region = $this->region;

        return $region.($region != null ? ' , ' : '').$this->country;
    }

    public function getLogoUrlAttribute(): string
    {
        return asset($this->logo ?: 'backend/image/default.png');
    }

    public function getBannerUrlAttribute(): string
    {
        return asset($this->banner ?: 'backend/image/default.png');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'user_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(JobRecord::class, 'company_id');
    }

    public function userPlan(): HasOne
    {
        return $this->hasOne(UserPlanRecord::class, 'company_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationTypeRecord::class, 'organization_type_id');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(IndustryTypeRecord::class, 'industry_type_id');
    }

    public function team_size(): BelongsTo
    {
        return $this->belongsTo(TeamSizeRecord::class, 'team_size_id');
    }

    public function bookmarkCandidates(): BelongsToMany
    {
        return $this->belongsToMany(CandidateRecord::class, 'bookmark_company', 'company_id', 'candidate_id')
            ->with('user')->withPivot('category_id')->withTimestamps();
    }

    /**
     * Bentuk identik dengan `CompanyResource` aplikasi lama; kolom yang tidak ada di tabel `companies` bernilai null.
     *
     * @return array<string,mixed>
     */
    public function toProfileResource(): ?array
    {
        $user = $this->user;

        if ($user === null) {
            return null;
        }

        return [
            'id' => $this->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'name' => $user->name,
            'first_name' => null,
            'last_name' => null,
            'role_id' => null,
            'profession_id' => null,
            'experience_id' => null,
            'education_id' => null,
            'title' => null,
            'gender' => null,
            'website' => $this->website,
            'cv' => null,
            'bio' => $this->bio,
            'marital_status' => null,
            'birth_date' => null,
            'cv_visibility' => null,
            'profile_complete' => null,
            'address' => $this->address,
            'neighborhood' => $this->neighborhood,
            'locality' => $this->locality,
            'place' => $this->place,
            'district' => $this->district,
            'postcode' => $this->postcode,
            'region' => $this->region,
            'country' => $this->country,
            'photo_url' => $this->logo_url,
            'user_plan' => $this->userPlan?->toArray(),
            'social' => $user->socialInfo->map(fn ($item) => [
                'social_media' => $item->social_media,
                'url' => $item->url,
            ])->all(),
            'contactinfo' => $user->contactInfo?->makeHidden(['id', 'user_id', 'created_at', 'updated_at'])->toArray(),
        ];
    }

    /**
     * Item daftar publik perusahaan (butuh relasi `user` dan hitungan `activejobs`).
     *
     * @return array<string,mixed>
     */
    public function toListItem(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'username' => $this->user?->username,
            'country' => $this->country,
            'logo_url' => $this->logo_url,
            'activejobs' => $this->getAttribute('activejobs'),
        ];
    }
}
