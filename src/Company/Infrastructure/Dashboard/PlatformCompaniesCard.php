<?php

namespace Src\Company\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\CompanyStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;

/** The companies of the platform: all, the active ones, and how many joined on each of the last 30 days. */
final readonly class PlatformCompaniesCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function key(): string
    {
        return 'companies';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function build(DashboardScope $scope): array
    {
        $window = DashboardWindow::at($this->clock->now());
        $perDay = [];

        foreach ($this->connection->table('company_companies')
            ->where('created_at', '>=', $window->fromUtc->format('Y-m-d H:i:s'))
            ->groupBy('day')
            ->get([$this->connection->raw(DashboardWindow::dayOf('created_at').' as day'), $this->connection->raw('COUNT(*) as total')]) as $row) {
            $perDay[(string) $row->day] = (int) $row->total;
        }

        return [
            'total' => $this->connection->table('company_companies')->count(),
            'active' => $this->connection->table('company_companies')->where('status', CompanyStatus::Active->value)->count(),
            'daily' => $window->series($perDay),
        ];
    }
}
