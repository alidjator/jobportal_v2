<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/** Penerima notifikasi database untuk tabel `users`; tipe morph sama dengan aplikasi lama. */
class NotifiableUser extends Model
{
    use Notifiable;

    protected $table = 'users';

    public function getMorphClass(): string
    {
        return 'App\Models\User';
    }
}
