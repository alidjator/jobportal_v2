<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** Tabel `contacts`. */
class Contact extends Model
{
    protected $table = 'contacts';

    protected $guarded = [];
}
