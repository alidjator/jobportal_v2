<?php

namespace Src\Campaign\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Campaign\Application\CampaignResults;
use Src\Campaign\Application\CampaignStatistics;
use Src\Campaign\Domain\Campaign;
use Src\Shared\Application\Dashboard\DashboardWindow;

/**
 * The counts of a campaign, read straight from `identity_users` and `recruitment_applications` (decided 2026-10-10: one reporting query in this context, a documented
 * exception to the rule that a context reads only its own tables; it never writes). Accounts are matched on the lower-cased code (the code of an imported account keeps
 * its case); an anonymised account keeps its code, so the figures survive an erasure.
 */
final readonly class DatabaseCampaignStatistics implements CampaignStatistics
{
    public function __construct(private ConnectionInterface $connection) {}

    public function registrations(string $code): int
    {
        return $this->registered($code)->count();
    }

    public function results(Campaign $campaign, DashboardWindow $window): CampaignResults
    {
        $applications = $this->connection->table('recruitment_applications as a')
            ->whereIn('a.user_id', $this->registered($campaign->code)->select('u.id'))
            ->when($campaign->companyId !== null, fn ($query) => $query->where('a.company_id', $campaign->companyId))
            ->first([$this->connection->raw('COUNT(DISTINCT a.user_id) as applicants'), $this->connection->raw('COUNT(a.id) as applications')]);

        $perDay = [];

        foreach ($this->registered($campaign->code)
            ->where('u.created_at', '>=', $window->fromUtc->format('Y-m-d H:i:s'))
            ->groupBy($this->connection->raw(DashboardWindow::dayOf('u.created_at')))
            ->get([$this->connection->raw(DashboardWindow::dayOf('u.created_at').' as day'), $this->connection->raw('COUNT(*) as total')]) as $row) {
            $perDay[(string) $row->day] = (int) $row->total;
        }

        return new CampaignResults(
            $this->registrations($campaign->code),
            (int) $applications->applicants,
            (int) $applications->applications,
            $window->from,
            $window->today,
            $window->series($perDay),
        );
    }

    private function registered(string $code): Builder
    {
        return $this->connection->table('identity_users as u')->whereRaw('lower(u.registration_campaign_code) = ?', [mb_strtolower($code)]);
    }
}
