<?php

namespace Src\Company\Infrastructure\Persistence;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Company\Application\Verification\CompanyVerificationBadge;
use Src\Shared\Application\Clock;

/** Two set-based updates (withdraw, then grant), so recalculating every company never loops over companies. */
final readonly class DatabaseCompanyVerificationBadge implements CompanyVerificationBadge
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function recalculateAll(): void
    {
        $this->recalculate(null);
    }

    public function recalculateFor(int $companyId): void
    {
        $this->recalculate($companyId);
    }

    private function recalculate(?int $companyId): void
    {
        $time = $this->clock->now()->format(self::TIME_FORMAT);
        $anyRequiredType = $this->requiredTypes($this->connection->table('company_document_types'))->exists();

        // Withdraw: verified, but there is no required type any more or one required document is not approved.
        $withdraw = $this->companies($companyId)->whereNotNull('verified_at');

        if ($anyRequiredType) {
            $withdraw->whereExists($this->lacksApprovedRequiredDocument());
        }

        $withdraw->update(['verified_at' => null, 'updated_at' => $time]);

        // Grant: not verified yet, at least one required type exists and every one of them is approved.
        if ($anyRequiredType) {
            $this->companies($companyId)
                ->whereNull('verified_at')
                ->whereNotExists($this->lacksApprovedRequiredDocument())
                ->update(['verified_at' => $time, 'updated_at' => $time]);
        }
    }

    private function companies(?int $companyId): Builder
    {
        $query = $this->connection->table('company_companies');

        return $companyId === null ? $query : $query->where('company_companies.id', $companyId);
    }

    private function requiredTypes(Builder $query, string $alias = 'company_document_types'): Builder
    {
        return $query->where("{$alias}.is_active", true)->where("{$alias}.is_required", true);
    }

    /** Matches a company that has an active required type without an approved document of its own. */
    private function lacksApprovedRequiredDocument(): Closure
    {
        return function (Builder $types): void {
            $types->selectRaw('1')->from('company_document_types as t');
            $this->requiredTypes($types, 't');

            $types->whereNotExists(function (Builder $documents): void {
                $documents->selectRaw('1')
                    ->from('company_documents as d')
                    ->whereColumn('d.company_id', 'company_companies.id')
                    ->whereColumn('d.document_type_id', 't.id')
                    ->where('d.status', 'approved');
            });
        };
    }
}
