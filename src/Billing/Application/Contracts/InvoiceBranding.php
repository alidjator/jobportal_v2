<?php

namespace Src\Billing\Application\Contracts;

interface InvoiceBranding
{
    /** Logo untuk invoice (data URI atau URL), null bila tidak tersedia. */
    public function logo(): ?string;

    public function appName(): string;
}
