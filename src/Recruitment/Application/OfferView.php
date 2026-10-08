<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferStatus;

/** An offer with its status as of now (a pending offer past its deadline shows as expired). */
final readonly class OfferView
{
    public function __construct(public Offer $offer, public OfferStatus $status) {}
}
