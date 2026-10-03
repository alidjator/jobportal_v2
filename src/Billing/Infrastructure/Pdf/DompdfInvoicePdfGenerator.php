<?php

namespace Src\Billing\Infrastructure\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Src\Billing\Application\Contracts\InvoicePdfGenerator;

final class DompdfInvoicePdfGenerator implements InvoicePdfGenerator
{
    public function generate(array $data): string
    {
        return Pdf::loadView('billing::invoice', $data)
            ->setOptions(['defaultFont' => 'sans-serif'])
            ->output();
    }
}
