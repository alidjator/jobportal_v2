<?php

namespace Src\Billing\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** Memetakan tabel `plans`. */
class PlanModel extends Model
{
    protected $table = 'plans';

    protected $guarded = [];

    protected $casts = [
        'frontend_show' => 'boolean',
        'recommended' => 'boolean',
    ];
}
