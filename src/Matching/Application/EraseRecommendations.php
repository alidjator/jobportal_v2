<?php

namespace Src\Matching\Application;

use Src\Matching\Domain\DeliveryRepository;
use Src\Shared\Application\Accounts\ErasureStep;

/** Deletes what was remembered about the emails of a user whose account was anonymised. Running it again does nothing. */
final readonly class EraseRecommendations implements ErasureStep
{
    public function __construct(private DeliveryRepository $deliveries) {}

    public function name(): string
    {
        return 'recommendations';
    }

    public function execute(int $userId): void
    {
        $this->deliveries->deleteOfUser($userId);
    }
}
