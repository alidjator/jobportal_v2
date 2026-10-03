<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tabel `jobs` (milik Recruitment) - hanya dibaca: dashboard perusahaan dan lowongan di halaman publik perusahaan. */
class JobRecord extends Model
{
    protected $table = 'jobs';

    protected $guarded = [];

    protected $appends = ['days_remaining', 'days_remaining_days', 'deadline_active', 'can_apply', 'full_address'];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'highlight' => 'boolean',
            'highlight_until' => 'date:Y-m-d',
            'can_apply' => 'boolean',
        ];
    }

    public function getFullAddressAttribute(): string
    {
        $region = $this->region;

        return $region.($region != null ? ' , ' : '').$this->country;
    }

    public function getDaysRemainingAttribute(): string
    {
        return Carbon::parse($this->deadline)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE, short: true, parts: 2);
    }

    public function getDaysRemainingDaysAttribute(): int
    {
        return (int) Carbon::now()->diffInDays($this->deadline, false);
    }

    public function getCanApplyAttribute(): bool
    {
        return $this->apply_on === 'app';
    }

    public function getDeadlineActiveAttribute(): bool
    {
        return Carbon::parse($this->deadline)->format('Y-m-d') >= Carbon::now()->toDateString();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyRecord::class, 'company_id');
    }

    public function job_type(): BelongsTo
    {
        return $this->belongsTo(JobTypeRecord::class, 'job_type_id');
    }

    public function appliedJobs(): HasMany
    {
        // Tabel `applied_jobs` (Recruitment), hanya untuk withCount pada dashboard.
        return $this->hasMany(AppliedJobRecord::class, 'job_id');
    }
}
