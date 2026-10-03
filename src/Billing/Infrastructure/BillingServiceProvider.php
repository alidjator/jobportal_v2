<?php

namespace Src\Billing\Infrastructure;

use Src\Billing\Application\Contracts\InvoiceBranding;
use Src\Billing\Application\Contracts\InvoicePdfGenerator;
use Src\Billing\Domain\BillingRepository;
use Src\Billing\Infrastructure\Pdf\DompdfInvoicePdfGenerator;
use Src\Billing\Infrastructure\Persistence\EloquentBillingRepository;
use Src\Shared\Infrastructure\ContextServiceProvider;

class BillingServiceProvider extends ContextServiceProvider
{
    public array $bindings = [
        BillingRepository::class => EloquentBillingRepository::class,
        InvoicePdfGenerator::class => DompdfInvoicePdfGenerator::class,
        InvoiceBranding::class => DatabaseInvoiceBranding::class,
    ];

    public function register(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 3).'/resources/views/billing', 'billing');
    }
}
