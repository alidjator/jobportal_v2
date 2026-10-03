<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** Tabel `testimonials`. */
class Testimonial extends Model
{
    protected $table = 'testimonials';

    protected $guarded = [];

    public function getImageAttribute(?string $image): string
    {
        return $image ?: 'backend/image/default.png';
    }
}
