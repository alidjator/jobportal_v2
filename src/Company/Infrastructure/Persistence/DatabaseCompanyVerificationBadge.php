<?php

namespace Src\Company\Infrastructure\Persistence;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Company\Application\Verification\BadgeChanges;
use Src\Company\Application\Verification\CompanyVerificationBadge;
use Src\Shared\Application\Clock;

/**
 * Two passes (withdraw, then grant). Each one reads the ids it will change with a row lock (callers run inside a transaction), then updates
 * them in chunks, so recalculating every company never loops over companies one by one and the lists it returns match what was written.
 */
final readonly class DatabaseCompanyVerificationBadge implements CompanyVerificationBadge
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const int CHUNK_SIZE = 1000;

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function recalculateAll(): BadgeChanges
    {
        return $this->recalculate(null);
    }

    public function recalculateFor(int $companyId): BadgeChanges
    {
        return $this->recalculate($companyId);
    }

    private function recalculate(?int $companyId): BadgeChanges
    {
        $time = $this->clock->now()->format(self::TIME_FORMAT);
        $anyRequiredType = $this->requiredTypes($this->connection->table('company_document_types'))->exists();

        // Withdraw: verified, but there is no required type any more or one required document is not approved.
        $withdraw = $this->companies($companyId)->whereNotNull('verified_at');

        if ($anyRequiredType) {
            $withdraw->whereExists($this->lacksApprovedRequiredDocument());
        }

        $withdrawn = $this->ids($withdraw);
        $this->write($withdrawn, null, $time);

        // Grant: not verified yet, at least one required type exists and every one of them is approved.
        $granted = [];

        if ($anyRequiredType) {
            $granted = $this->ids($this->companies($companyId)->whereNull('verified_at')->whereNotExists($this->lacksApprovedRequiredDocument()));
            $this->write($granted, $time, $time);
        }

        return new BadgeChanges($granted, $withdrawn);
    }

    private function companies(?int $companyId): Builder
    {
        $query = $this->connection->table('company_companies');

        return $companyId === null ? $query : $query->where('company_companies.id', $companyId);
    }

    /** @return list<int> */
    private function ids(Builder $query): array
    {
        return array_map('intval', $query->lockForUpdate()->pluck('company_companies.id')->all());
    }

    /** @param  list<int>  $ids */
    private function write(array $ids, ?string $verifiedAt, string $time): void
    {
        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $this->connection->table('company_companies')->whereIn('id', $chunk)->update(['verified_at' => $verifiedAt, 'updated_at' => $time]);
        }
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
