<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use Src\Recruitment\Domain\Offer;
use Src\Shared\Application\Tasks\TaskScope;

/** Of the offers waiting for an answer, the ones that expire within three days. */
final class OffersExpiringTask extends OffersWaitingTask
{
    public function key(): string
    {
        return 'offers_expiring';
    }

    public function order(): int
    {
        return 51;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/applications?filter[state]=offer_pending&filter[offer_expiring]=1';
    }

    protected function expiringWithinDays(): int
    {
        return Offer::EXPIRING_DAYS;
    }
}
