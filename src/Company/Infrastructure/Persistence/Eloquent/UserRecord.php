<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Tabel `users` (read-only di context Company); kolom rahasia/identitas provider disembunyikan. */
class UserRecord extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    protected $appends = ['image_url'];

    protected $hidden = [
        'password', 'remember_token', 'firebase_uid', 'google_id', 'facebook_id', 'provider', 'provider_id', 'resend_at',
    ];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime'];
    }

    public function getImageUrlAttribute(): string
    {
        return asset($this->image ?: 'backend/image/default.png');
    }

    public function company(): HasOne
    {
        return $this->hasOne(CompanyRecord::class, 'user_id');
    }

    public function contactInfo(): HasOne
    {
        return $this->hasOne(ContactInfoRecord::class, 'user_id');
    }

    public function socialInfo(): HasMany
    {
        return $this->hasMany(SocialLinkRecord::class, 'user_id');
    }
}
