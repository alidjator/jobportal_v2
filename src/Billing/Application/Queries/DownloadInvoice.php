<?php

namespace Src\Billing\Application\Queries;

use Src\Billing\Application\Contracts\InvoiceBranding;
use Src\Billing\Application\Contracts\InvoicePdfGenerator;
use Src\Billing\Domain\BillingRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class DownloadInvoice
{
    public function __construct(
        private BillingRepository $repository,
        private InvoicePdfGenerator $pdf,
        private InvoiceBranding $branding,
    ) {}

    /** @return array{filename: string, content: string} */
    public function __invoke(int $userId, int $transactionId): array
    {
        $companyId = $this->repository->companyIdOfUser($userId) ?? throw EntityNotFound::of('Company');
        // Hanya pemilik transaksi yang boleh mengunduh (aplikasi lama tidak memeriksa).
        $transaction = $this->repository->invoiceTransaction($transactionId, $companyId)
            ?? throw EntityNotFound::of('Transaction', $transactionId);

        $content = $this->pdf->generate([
            'transaction' => $transaction,
            'logo' => $this->branding->logo(),
            'appName' => $this->branding->appName(),
        ]);

        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $transaction['order_id']);

        return ['filename' => 'invoice_'.$safe.'.pdf', 'content' => $content];
    }
}
