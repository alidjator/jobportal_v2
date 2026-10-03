<?php

namespace Src\Billing\Application\Contracts;

interface InvoicePdfGenerator
{
    /**
     * @param  array<string,mixed>  $data  data untuk view invoice
     * @return string biner PDF
     */
    public function generate(array $data): string;
}
