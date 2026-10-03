<?php

namespace Src\Billing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Billing\Domain\BillingRepository;
use Src\Billing\Infrastructure\Persistence\Eloquent\EarningModel;
use Src\Billing\Infrastructure\Persistence\Eloquent\PlanModel;
use Src\Billing\Infrastructure\Persistence\Eloquent\UserPlanModel;

final class EloquentBillingRepository implements BillingRepository
{
    public function activePlans(): array
    {
        return PlanModel::query()->where('frontend_show', true)->get()->toArray();
    }

    public function companyIdOfUser(int $userId): ?int
    {
        // Tabel `companies` milik context Company; dibaca read-only hanya untuk memetakan user -> company.
        $id = DB::table('companies')->where('user_id', $userId)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function userPlanOfCompany(int $companyId): ?array
    {
        return UserPlanModel::query()->with('plan')->ofCompany($companyId)->first()?->toArray();
    }

    public function transactionsOfCompany(int $companyId, int $perPage): array
    {
        return EarningModel::query()
            ->with('plan:id,label', 'manualPayment:id,name')
            ->ofCompany($companyId)
            ->latest()
            ->paginate($perPage)
            ->toArray();
    }

    public function invoiceTransaction(int $transactionId, int $companyId): ?array
    {
        $earning = EarningModel::query()->with('plan')->ofCompany($companyId)->find($transactionId);
        if ($earning === null) {
            return null;
        }

        // `users`, `contact_infos`, `companies` milik context Identity/Company; dibaca read-only untuk kontak di invoice.
        $user = DB::table('companies')
            ->join('users', 'users.id', '=', 'companies.user_id')
            ->leftJoin('contact_infos', 'contact_infos.user_id', '=', 'users.id')
            ->where('companies.id', $companyId)
            ->select('users.name', 'users.email', 'contact_infos.phone', 'companies.address')
            ->first();

        return [
            ...$earning->toArray(),
            'company' => [
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'phone' => $user->phone ?? null,
                'address' => $user->address ?? null,
            ],
        ];
    }
}
