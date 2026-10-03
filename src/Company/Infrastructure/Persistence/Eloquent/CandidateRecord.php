<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tabel `candidates` (milik CandidateProfile) - hanya dibaca untuk daftar kandidat yang di-bookmark. */
class CandidateRecord extends Model
{
    protected $table = 'candidates';

    protected $guarded = [];

    protected $appends = ['full_address'];

    public function getPhotoAttribute(?string $photo): string
    {
        return asset($photo ?: 'backend/image/default.png');
    }

    public function getFullAddressAttribute(): string
    {
        $region = $this->region;

        return $region.($region != null ? ' , ' : '').$this->country;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'user_id');
    }

    public function profession(): BelongsTo
    {
        return $this->belongsTo(ProfessionRecord::class, 'profession_id');
    }
}
