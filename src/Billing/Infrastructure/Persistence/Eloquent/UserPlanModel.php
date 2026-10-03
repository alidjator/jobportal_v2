<?php

namespace Src\Billing\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Memetakan tabel `user_plans`. */
class UserPlanModel extends Model
{
    protected $table = 'user_plans';

    protected $guarded = [];

    /** Plan milik perusahaan tertentu (pengganti scope lama `apiCompanyData`). */
    public function scopeOfCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanModel::class, 'plan_id');
    }
}
