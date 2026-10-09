<?php

namespace Src\Campaign\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Campaign\Domain\Campaign;
use Src\Campaign\Domain\CampaignQuery;
use Src\Campaign\Domain\CampaignRepository;
use Src\Campaign\Domain\CampaignStatus;
use Src\Campaign\Domain\CampaignType;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Infrastructure\Persistence\TextSearch;
use stdClass;

final readonly class DatabaseCampaignRepository implements CampaignRepository
{
    private const string TABLE = 'campaign_campaigns';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(Campaign $campaign): Campaign
    {
        try {
            $id = (int) $this->connection->table(self::TABLE)->insertGetId([
                'company_id' => $campaign->companyId,
                'code' => $campaign->code,
                'name' => $campaign->name,
                'type' => $campaign->type->value,
                'starts_on' => $campaign->startsOn,
                'ends_on' => $campaign->endsOn,
                'is_active' => $campaign->active,
                'created_by_user_id' => $campaign->createdByUserId,
                'created_by_admin_id' => $campaign->createdByAdminId,
                'created_at' => $campaign->createdAt->format(self::TIME_FORMAT),
                'updated_at' => $campaign->updatedAt->format(self::TIME_FORMAT),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('This campaign code is already used.', 'campaign-code-taken');
        }

        return new Campaign(
            $campaign->companyId, $campaign->code, $campaign->name, $campaign->type, $campaign->startsOn, $campaign->endsOn, $campaign->active,
            $campaign->createdByUserId, $campaign->createdByAdminId, $campaign->createdAt, $campaign->updatedAt, $id,
        );
    }

    public function update(Campaign $campaign): void
    {
        $this->connection->table(self::TABLE)->where('id', $campaign->id)->update([
            'name' => $campaign->name,
            'type' => $campaign->type->value,
            'starts_on' => $campaign->startsOn,
            'ends_on' => $campaign->endsOn,
            'is_active' => $campaign->active,
            'updated_at' => $campaign->updatedAt->format(self::TIME_FORMAT),
        ]);
    }

    public function delete(int $campaignId): void
    {
        $this->connection->table(self::TABLE)->where('id', $campaignId)->delete();
    }

    public function find(int $campaignId, bool $forUpdate = false): ?Campaign
    {
        $query = $this->connection->table(self::TABLE)->where('id', $campaignId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toCampaign($row);
    }

    public function findByCode(string $code): ?Campaign
    {
        $row = $this->connection->table(self::TABLE)->where('code', $code)->first();

        return $row === null ? null : $this->toCampaign($row);
    }

    public function page(CampaignQuery $query, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE);

        if ($query->companyId !== null) {
            $builder->where('company_id', $query->companyId);
        }

        if ($query->type !== null) {
            $builder->where('type', $query->type->value);
        }

        if ($query->search !== null && $query->search !== '') {
            $builder->where(function (Builder $search) use ($query): void {
                TextSearch::contains($search, 'name', $query->search);
                TextSearch::contains($search, 'code', $query->search, 'or');
            });
        }

        if ($query->status !== null) {
            $this->whereStatus($builder, $query->status, $query->today);
        }

        $total = $builder->count();
        $rows = $builder->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): Campaign => $this->toCampaign($row), $rows->all()), $total, $request);
    }

    private function whereStatus(Builder $builder, CampaignStatus $status, string $today): void
    {
        match ($status) {
            CampaignStatus::Inactive => $builder->where('is_active', false),
            CampaignStatus::Scheduled => $builder->where('is_active', true)->where('starts_on', '>', $today),
            CampaignStatus::Ended => $builder->where('is_active', true)->where('ends_on', '<', $today),
            CampaignStatus::Active => $builder->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
                ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today)),
        };
    }

    private function toCampaign(stdClass $row): Campaign
    {
        return new Campaign(
            $row->company_id === null ? null : (int) $row->company_id,
            (string) $row->code,
            (string) $row->name,
            CampaignType::from((string) $row->type),
            $row->starts_on === null ? null : (string) $row->starts_on,
            $row->ends_on === null ? null : (string) $row->ends_on,
            (bool) $row->is_active,
            $row->created_by_user_id === null ? null : (int) $row->created_by_user_id,
            $row->created_by_admin_id === null ? null : (int) $row->created_by_admin_id,
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row->updated_at, new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }
}
