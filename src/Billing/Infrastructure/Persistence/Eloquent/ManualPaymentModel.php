<?php

namespace Src\Billing\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/** Memetakan tabel `manual_payments`. */
class ManualPaymentModel extends Model
{
    protected $table = 'manual_payments';

    protected $guarded = [];
}
