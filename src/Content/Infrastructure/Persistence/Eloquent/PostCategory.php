<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** Tabel `post_categories`. */
class PostCategory extends Model
{
    protected $table = 'post_categories';

    protected $guarded = [];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): string
    {
        return asset($this->image ?: 'backend/image/default.png');
    }
}
