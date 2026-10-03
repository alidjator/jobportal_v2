<?php

namespace Src\Company\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** Tabel `user_plans` (milik Billing) - hanya dibaca untuk `user_plan` pada profil dan batas lihat CV kandidat. */
class UserPlanRecord extends Model
{
    protected $table = 'user_plans';

    protected $guarded = [];
}
