<?php

namespace Src\Billing\Domain;

interface BillingRepository
{
    /** Plan yang tampil di frontend (`frontend_show = 1`). @return list<array<string,mixed>> */
    public function activePlans(): array;

    public function companyIdOfUser(int $userId): ?int;

    /** Plan aktif milik perusahaan beserta relasi `plan`, atau null. @return array<string,mixed>|null */
    public function userPlanOfCompany(int $companyId): ?array;

    /**
     * Transaksi perusahaan (terbaru dulu) dalam bentuk paginator Laravel.
     *
     * @return array<string,mixed>
     */
    public function transactionsOfCompany(int $companyId, int $perPage): array;

    /**
     * Data transaksi + plan + kontak perusahaan untuk invoice, hanya bila milik `$companyId`.
     *
     * @return array<string,mixed>|null
     */
    public function invoiceTransaction(int $transactionId, int $companyId): ?array;
}
