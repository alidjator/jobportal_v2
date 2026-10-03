<?php

namespace Src\Billing\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Memetakan tabel `earnings` (transaksi). */
class EarningModel extends Model
{
    protected $table = 'earnings';

    protected $guarded = [];

    public function scopeOfCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanModel::class, 'plan_id');
    }

    public function manualPayment(): BelongsTo
    {
        return $this->belongsTo(ManualPaymentModel::class, 'manual_payment_id');
    }
}
