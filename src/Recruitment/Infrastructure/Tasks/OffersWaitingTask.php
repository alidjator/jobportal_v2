<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\OfferStatus;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** Offers that were sent and not answered yet; `expiringWithinDays` narrows them to the ones that end soon. */
class OffersWaitingTask implements TaskProvider
{
    public function __construct(protected ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'offers_waiting';
    }

    public function order(): int
    {
        return 50;
    }

    public function permission(): string
    {
        return CompanyPermission::OffersManage->value;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/applications?filter[state]=offer_pending';
    }

    public function count(TaskScope $scope): int
    {
        return HiringManagerBound::limit($this->connection->table('recruitment_offers as o')->join('recruitment_applications as a', 'a.id', '=', 'o.application_id'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn ($query) => $query->whereIn('a.job_id', $scope->jobIds ?? []))
            ->where('o.status', OfferStatus::Pending->value)
            ->when($this->expiringWithinDays() !== null, fn ($query) => $query->where('o.expires_at', '<=', $scope->now->modify('+'.$this->expiringWithinDays().' days')->format('Y-m-d H:i:s')))
            ->count();
    }

    protected function expiringWithinDays(): ?int
    {
        return null;
    }
}
