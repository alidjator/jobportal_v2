<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tabel `post_comments` (tanpa flag approval di aplikasi lama). */
class PostComment extends Model
{
    protected $table = 'post_comments';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(CommentAuthor::class, 'author_id', 'id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PostComment::class, 'parent_id')->latest();
    }
}
