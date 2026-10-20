<?php

namespace Src\Company\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Application\CompanyPermissions;
use Src\Company\Domain\NameChangeStatus;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The company name change requests that wait for a decision (`docs/design/phase-8-admin-queue-cards.md`). A count only. Shown only to an admin who may manage companies. */
final readonly class NameChangesQueueCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'name_changes_queue';
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
        return ['pending' => $this->connection->table('company_name_change_requests')->where('status', NameChangeStatus::Pending->value)->count()];
    }
}
