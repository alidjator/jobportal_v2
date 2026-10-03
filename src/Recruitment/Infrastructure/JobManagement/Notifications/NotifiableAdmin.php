<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/** Penerima notifikasi database untuk tabel `admins`; tipe morph sama dengan aplikasi lama. */
class NotifiableAdmin extends Model
{
    use Notifiable;

    protected $table = 'admins';

    public function getMorphClass(): string
    {
        return 'App\Models\Admin';
    }
}
