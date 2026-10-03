<?php

namespace Src\Identity\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Memetakan tabel `users` yang sudah ada (DB bersama dengan aplikasi lama).
 */
class UserModel extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Sama dengan accessor `image_url` model User aplikasi lama. */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => asset($this->image ?: 'backend/image/default.png'));
    }
}
