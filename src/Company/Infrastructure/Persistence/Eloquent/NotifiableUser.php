<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/** Penerima notifikasi database; `notifiable_type` dipertahankan `App\Models\User` agar kompatibel dengan data lama. */
class NotifiableUser extends Model
{
    use Notifiable;

    protected $table = 'users';

    public function getMorphClass(): string
    {
        return 'App\Models\User';
    }
}
