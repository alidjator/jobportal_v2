<?php

namespace Src\Company\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Application\CompanyPermissions;
use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\DocumentStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/**
 * What needs a platform admin among the companies: the ones with a document that waits for a review, and the ones without a company admin (for
 * example after the last admin's account was erased), up to ten of them by name so the admin can invite a new one. A company whose first
 * company admin was invited and can still accept is not counted. Shown only to an admin who may manage companies.
 */
final readonly class CompanyVerificationQueueCard implements DashboardCardProvider
{
    private const int LISTED = 10;

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

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
        $withoutAdmin = $this->connection->table('company_companies as c')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('company_members as m')->whereColumn('m.company_id', 'c.id')->where('m.role', CompanyRole::CompanyAdmin->value))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('company_invitations as i')
                ->whereColumn('i.company_id', 'c.id')
                ->where('i.role', CompanyRole::CompanyAdmin->value)
                ->whereNull('i.accepted_at')
                ->whereNull('i.revoked_at')
                ->where('i.expires_at', '>', $this->clock->now()->format('Y-m-d H:i:s')));

        return [
            'waiting_for_review' => (int) $this->connection->table('company_documents')
                ->where('status', DocumentStatus::Pending->value)
                ->distinct()
                ->count('company_id'),
            'without_admin' => (clone $withoutAdmin)->count(),
            'without_admin_companies' => (clone $withoutAdmin)->orderBy('c.id')->limit(self::LISTED)->get(['c.id', 'c.name'])
                ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->all(),
        ];
    }
}
