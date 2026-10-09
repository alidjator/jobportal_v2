<?php

namespace Src\Recruitment\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;

/** All applications on the platform, and how many arrived on each of the last 30 days. */
final readonly class PlatformApplicationsCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function key(): string
    {
        return 'applications';
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

        foreach ($this->connection->table('recruitment_applications')
            ->where('applied_at', '>=', $window->fromUtc->format('Y-m-d H:i:s'))
            ->groupBy('day')
            ->get([$this->connection->raw(DashboardWindow::dayOf('applied_at').' as day'), $this->connection->raw('COUNT(*) as total')]) as $row) {
            $perDay[(string) $row->day] = (int) $row->total;
        }

        return ['total' => $this->connection->table('recruitment_applications')->count(), 'daily' => $window->series($perDay)];
    }
}
