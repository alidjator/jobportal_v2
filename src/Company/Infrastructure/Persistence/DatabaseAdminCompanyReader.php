<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Company\Application\Admin\AdminCompanyReader;
use Src\Company\Application\Admin\CompanyCounts;
use Src\Company\Application\Admin\CompanyListItem;
use Src\Company\Application\Admin\CompanyListQuery;
use Src\Company\Application\Admin\CompanyListSort;
use Src\Company\Domain\CompanyStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Infrastructure\Persistence\NullOrder;
use Src\Shared\Infrastructure\Persistence\TextSearch;
use stdClass;

final readonly class DatabaseAdminCompanyReader implements AdminCompanyReader
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function page(CompanyListQuery $query, PageRequest $request): Page
    {
        $filtered = $this->connection->table('company_companies');
        $this->applyFilters($filtered, $query);

        $total = (clone $filtered)->count();

        $members = $this->connection->table('company_members')
            ->selectRaw('count(*)')
            ->whereColumn('company_members.company_id', 'company_companies.id');

        $builder = $filtered
            ->select(['company_companies.id', 'name', 'slug', 'status', 'verified_at', 'created_at'])
            ->selectSub($members, 'members_count');

        [$column, $direction] = match ($query->sort) {
            CompanyListSort::NameAscending => ['name', 'asc'],
            CompanyListSort::NameDescending => ['name', 'desc'],
            CompanyListSort::OldestFirst => ['created_at', 'asc'],
            CompanyListSort::NewestFirst => ['created_at', 'desc'],
        };

        $rows = $builder->orderByRaw(NullOrder::sql($column, $direction))->orderBy('company_companies.id', $direction)
            ->offset($request->offset())->limit($request->perPage)->get()->all();

        return new Page(array_map(fn (stdClass $row): CompanyListItem => $this->toItem($row), $rows), $total, $request);
    }

    public function countByStatus(CompanyListQuery $query): array
    {
        $builder = $this->connection->table('company_companies');
        $this->applyFilters($builder, new CompanyListQuery($query->search, null, $query->verified));

        $counts = [];

        foreach ($builder->selectRaw('status, count(*) as total')->groupBy('status')->get() as $row) {
            $counts[(string) $row->status] = (int) $row->total;
        }

        return $counts;
    }

    public function counts(int $companyId, DateTimeImmutable $now): CompanyCounts
    {
        return new CompanyCounts(
            $this->connection->table('company_members')->where('company_id', $companyId)->count(),
            $this->connection->table('company_invitations')
                ->where('company_id', $companyId)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now->format(self::TIME_FORMAT))
                ->count(),
        );
    }

    private function applyFilters(Builder $builder, CompanyListQuery $query): void
    {
        if ($query->search !== null && $query->search !== '') {
            $builder->where(fn (Builder $match) => TextSearch::contains(TextSearch::contains($match, 'name', $query->search), 'slug', $query->search, 'or'));
        }

        if ($query->status !== null) {
            $builder->where('status', $query->status->value);
        }

        if ($query->verified === true) {
            $builder->whereNotNull('verified_at');
        } elseif ($query->verified === false) {
            $builder->whereNull('verified_at');
        }
    }

    private function toItem(stdClass $row): CompanyListItem
    {
        return new CompanyListItem(
            (int) $row->id,
            (string) $row->name,
            (string) $row->slug,
            CompanyStatus::from($row->status),
            $row->verified_at !== null,
            (int) $row->members_count,
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }
}
