<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\CheckType;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Recruitment\Domain\Offer;
use Src\Shared\Application\Clock;

/** Adds the status as of now to offers. */
final readonly class OfferViews
{
    public function __construct(private Clock $clock, private CheckTypeRepository $checkTypes) {}

    /**
     * @param  list<Offer>  $offers
     * @return list<OfferView>
     */
    public function of(array $offers): array
    {
        $now = $this->clock->now();
        $ids = array_values(array_unique(array_merge(...array_map(static fn (Offer $offer): array => $offer->conditionCheckTypeIds, $offers))));
        $names = $ids === [] ? [] : array_map(static fn (CheckType $type): string => $type->name, $this->checkTypes->byIds($ids));

        return array_map(
            static fn (Offer $offer): OfferView => new OfferView($offer, $offer->statusAt($now), array_intersect_key($names, array_flip($offer->conditionCheckTypeIds))),
            $offers,
        );
    }

    public function one(Offer $offer): OfferView
    {
        return $this->of([$offer])[0];
    }
}
