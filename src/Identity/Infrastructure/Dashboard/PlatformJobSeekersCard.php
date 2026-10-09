<?php

namespace Src\Identity\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;

/** The job seekers of the platform (accounts not anonymised), and how many signed up on each of the last 30 days. */
final readonly class PlatformJobSeekersCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function key(): string
    {
        return 'job_seekers';
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

        foreach ($this->seekers()
            ->where('created_at', '>=', $window->fromUtc->format('Y-m-d H:i:s'))
            ->groupBy('day')
            ->get([$this->connection->raw(DashboardWindow::dayOf('created_at').' as day'), $this->connection->raw('COUNT(*) as total')]) as $row) {
            $perDay[(string) $row->day] = (int) $row->total;
        }

        return ['total' => $this->seekers()->count(), 'daily' => $window->series($perDay)];
    }

    private function seekers(): Builder
    {
        return $this->connection->table('identity_users')->where('type', UserType::JobSeeker->value)->whereNull('anonymized_at');
    }
}
