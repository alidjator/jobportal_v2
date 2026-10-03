<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** Tabel `users` (read-only, milik context Identity): hanya untuk menampilkan penulis komentar. */
class CommentAuthor extends Model
{
    protected $table = 'users';

    protected $appends = ['image_url'];

    protected $hidden = ['password', 'remember_token'];

    public function getImageUrlAttribute(): string
    {
        return asset($this->image ?: 'backend/image/default.png');
    }
}
