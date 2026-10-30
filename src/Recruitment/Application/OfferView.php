<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferStatus;

/** An offer with its status as of now (a pending offer past its deadline shows as expired), and the names of the check types it depends on (id => name). */
final readonly class OfferView
{
    /** @param  array<int, string>  $conditionChecks */
    public function __construct(public Offer $offer, public OfferStatus $status, public array $conditionChecks = []) {}
}
