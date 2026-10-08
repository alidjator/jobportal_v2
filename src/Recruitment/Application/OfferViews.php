<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Offer;
use Src\Shared\Application\Clock;

/** Adds the status as of now to offers. */
final readonly class OfferViews
{
    public function __construct(private Clock $clock) {}

    /**
     * @param  list<Offer>  $offers
     * @return list<OfferView>
     */
    public function of(array $offers): array
    {
        $now = $this->clock->now();

        return array_map(static fn (Offer $offer): OfferView => new OfferView($offer, $offer->statusAt($now)), $offers);
    }

    public function one(Offer $offer): OfferView
    {
        return $this->of([$offer])[0];
    }
}
