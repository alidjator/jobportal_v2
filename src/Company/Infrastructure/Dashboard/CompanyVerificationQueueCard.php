<?php

namespace Src\Company\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Application\CompanyPermissions;
use Src\Company\Domain\DocumentStatus;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The companies with a document that waits for a review; shown only to an admin who may manage companies. */
final readonly class CompanyVerificationQueueCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'companies_queue';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin];
    }

    public function permission(): string
    {
        return CompanyPermissions::COMPANIES_MANAGE;
    }

    public function build(DashboardScope $scope): array
    {
        return [
            'waiting_for_review' => (int) $this->connection->table('company_documents')
                ->where('status', DocumentStatus::Pending->value)
                ->distinct()
                ->count('company_id'),
        ];
    }
}
