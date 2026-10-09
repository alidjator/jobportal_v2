<?php

namespace Src\Matching\Application;

use Src\Matching\Domain\DeliveryRepository;

/** Deletes what was remembered about the emails of a user whose account was anonymised. Running it again does nothing. */
final readonly class EraseRecommendations
{
    public function __construct(private DeliveryRepository $deliveries) {}

    public function execute(int $userId): void
    {
        $this->deliveries->deleteOfUser($userId);
    }
}
