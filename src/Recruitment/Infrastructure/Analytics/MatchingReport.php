<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\MatchBand;
use Src\Shared\Application\Analytics\AnalyticsReportProvider;
use Src\Shared\Application\Analytics\AnalyticsScope;
use Src\Shared\Application\Analytics\AnalyticsTable;
use Src\Shared\Application\Dashboard\DashboardAudience;

/**
 * Matching quality: what happened to applications by the band of their match score when they were submitted (`advanced` counts the applications that were taken out
 * of the first stage into a later one), and how applications that came from an invitation did
 * against the others. The score and the source exist only for applications submitted after they were recorded, so older ones show as `unknown`.
 */
final readonly class MatchingReport implements AnalyticsReportProvider
{
    public function __construct(private ConnectionInterface $connection, private DailySummaries $summaries) {}

    public function key(): string
    {
        return 'matching';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Company];
    }

    public function build(AnalyticsScope $scope): array
    {
        return [$this->bands($scope), $this->invitations($scope)];
    }

    private function bands(AnalyticsScope $scope): AnalyticsTable
    {
        $bands = [];

        foreach (MatchBand::cases() as $band) {
            $bands[$band->value] = ['band' => $band->value, 'applications' => 0, 'advanced' => 0, 'hired' => 0, 'rejected' => 0, 'advance_rate' => null, 'hire_rate' => null];
        }

        foreach ($this->summaries->applications($scope)->groupBy('match_band')->get(['match_band', $this->connection->raw('SUM(applications) as total')]) as $row) {
            $bands[(string) $row->match_band]['applications'] = (int) $row->total;
        }

        foreach ($this->summaries->transitions($scope)->groupBy('match_band')->get([
            'match_band',
            $this->connection->raw("SUM(CASE WHEN from_type = 'applied' AND to_type IN ('screening', 'interview', 'offer', 'hired') THEN transitions ELSE 0 END) as advanced"),
            $this->connection->raw("SUM(CASE WHEN to_type = 'hired' THEN transitions ELSE 0 END) as hired"),
            $this->connection->raw("SUM(CASE WHEN to_type = 'rejected' THEN transitions ELSE 0 END) as rejected"),
        ]) as $row) {
            $band = (string) $row->match_band;
            $bands[$band]['advanced'] = (int) $row->advanced;
            $bands[$band]['hired'] = (int) $row->hired;
            $bands[$band]['rejected'] = (int) $row->rejected;
        }

        foreach ($bands as &$band) {
            $band['advance_rate'] = Figures::ratio($band['advanced'], $band['applications']);
            $band['hire_rate'] = Figures::ratio($band['hired'], $band['applications']);
        }

        unset($band);

        return new AnalyticsTable('bands', ['band'], ['applications', 'advanced', 'hired', 'rejected', 'advance_rate', 'hire_rate'], array_values($bands), 'applications');
    }

    private function invitations(AnalyticsScope $scope): AnalyticsTable
    {
        $groups = ['invitation' => ['origin' => 'invitation', 'applications' => 0, 'hired' => 0, 'hire_rate' => null], 'other' => ['origin' => 'other', 'applications' => 0, 'hired' => 0, 'hire_rate' => null]];

        foreach ($this->summaries->applications($scope)->groupBy('source')->get(['source', $this->connection->raw('SUM(applications) as total')]) as $row) {
            $groups[$row->source === 'invitation' ? 'invitation' : 'other']['applications'] += (int) $row->total;
        }

        foreach ($this->summaries->transitions($scope)->where('to_type', 'hired')->groupBy('source')->get(['source', $this->connection->raw('SUM(transitions) as total')]) as $row) {
            $groups[$row->source === 'invitation' ? 'invitation' : 'other']['hired'] += (int) $row->total;
        }

        foreach ($groups as &$group) {
            $group['hire_rate'] = Figures::ratio($group['hired'], $group['applications']);
        }

        unset($group);

        return new AnalyticsTable('invitations', ['origin'], ['applications', 'hired', 'hire_rate'], array_values($groups), 'applications');
    }
}
