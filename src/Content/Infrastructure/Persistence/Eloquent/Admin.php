<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/** Tabel `admins` (read-only): penulis artikel & penerima notifikasi kontak. */
class Admin extends Model
{
    use Notifiable;

    protected $table = 'admins';

    protected $hidden = ['password', 'remember_token'];
}
