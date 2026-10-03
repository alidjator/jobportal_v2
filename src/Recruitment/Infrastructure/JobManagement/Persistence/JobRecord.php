<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Memetakan tabel `jobs` (milik aplikasi lama). Atribut turunan (`appends`) sama dengan model `Job` lama
 * supaya bentuk JSON identik. Tidak keluar dari layer Infrastructure.
 */
class JobRecord extends Model
{
    protected $table = 'jobs';

    protected $guarded = [];

    protected $appends = ['days_remaining', 'days_remaining_days', 'deadline_active', 'can_apply', 'full_address'];

    protected function casts(): array
    {
        return [
            'highlight_until' => 'date:Y-m-d',
            'featured_until' => 'date:Y-m-d',
        ];
    }

    /** Aktif hanya bila flag menyala dan (jika ada) `*_until` belum lewat. */
    private function promoted(mixed $flag, ?string $until): bool
    {
        if (! $flag) {
            return false;
        }

        return $until === null || Carbon::parse($until)->isFuture();
    }

    protected function featured(): Attribute
    {
        return Attribute::get(fn ($value) => $this->promoted($value, $this->attributes['featured_until'] ?? null));
    }

    protected function highlight(): Attribute
    {
        return Attribute::get(fn ($value) => $this->promoted($value, $this->attributes['highlight_until'] ?? null));
    }

    protected function daysRemaining(): Attribute
    {
        return Attribute::get(fn () => Carbon::parse($this->deadline)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE, short: true, parts: 2));
    }

    protected function daysRemainingDays(): Attribute
    {
        return Attribute::get(fn () => (int) Carbon::now()->diffInDays($this->deadline, false));
    }

    protected function deadlineActive(): Attribute
    {
        return Attribute::get(fn () => Carbon::parse($this->deadline)->format('Y-m-d') >= Carbon::now()->toDateString());
    }

    protected function canApply(): Attribute
    {
        return Attribute::get(fn () => $this->apply_on === 'app');
    }

    protected function fullAddress(): Attribute
    {
        return Attribute::get(function () {
            $region = $this->region;

            return $region.($region !== null ? ' , ' : '').$this->country;
        });
    }
}
