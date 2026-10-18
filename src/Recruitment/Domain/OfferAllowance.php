<?php

namespace Src\Recruitment\Domain;

/** One allowance line of an offer: a name and an amount in Rupiah per month. */
final readonly class OfferAllowance
{
    public const int NAME_MAX = 100;

    public const int AMOUNT_MAX = 1_000_000_000;

    public function __construct(public string $name, public int $amount) {}
}
