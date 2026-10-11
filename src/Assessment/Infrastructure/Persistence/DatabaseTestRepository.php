<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestOwner;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Domain\TestStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Infrastructure\Persistence\TextSearch;
use stdClass;

final readonly class DatabaseTestRepository implements TestRepository
{
    private const string TABLE = 'assessment_tests';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $id, bool $forUpdate = false): ?Test
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toTest($row);
    }

    public function save(Test $test): Test
    {
        $now = $this->clock->now();
        $values = [
            'title' => $test->title(),
            'description' => $test->description(),
            'instructions' => $test->instructions(),
            'measured_capabilities' => json_encode($test->capabilities(), JSON_THROW_ON_ERROR),
            'status' => $test->status()->value,
            'published_at' => $test->publishedAt(),
            'updated_at' => $now,
        ];

        if ($test->id !== null) {
            $this->connection->table(self::TABLE)->where('id', $test->id)->update($values);

            return $test;
        }

        $id = $this->connection->table(self::TABLE)->insertGetId($values + [
            'kind' => $test->kind()->value,
            'company_id' => $test->owner->companyId,
            'provider_id' => $test->owner->providerId,
            'created_by_user_id' => $test->createdByUserId,
            'created_by_admin_id' => $test->createdByAdminId,
            'created_at' => $now,
        ]);

        return $this->find((int) $id) ?? throw new \LogicException('The new test was not found.');
    }

    public function delete(int $id): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->delete();
    }

    public function pageOwnedBy(TestOwner $owner, ?TestStatus $status, ?string $search, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE);

        if ($owner->companyId !== null) {
            $builder->where('company_id', $owner->companyId);
        } elseif ($owner->providerId !== null) {
            $builder->where('provider_id', $owner->providerId);
        } else {
            $builder->whereNull('company_id')->whereNull('provider_id');
        }

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        return $this->page($builder, $search, $request, 'created_at', 'desc');
    }

    public function countLiveOfCompany(int $companyId): int
    {
        return $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('status', '<>', TestStatus::Archived->value)->count();
    }

    public function pageLibrary(?string $search, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE)
            ->where('kind', 'skill')->whereNull('company_id')->where('status', TestStatus::Published->value);

        return $this->page($builder, $search, $request, 'title', 'asc');
    }

    public function pageGrantedTo(int $companyId, ?string $search, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE)
            ->join('assessment_company_batteries as grants', 'grants.test_id', '=', self::TABLE.'.id')
            ->join('assessment_providers as providers', 'providers.id', '=', self::TABLE.'.provider_id')
            ->where('grants.company_id', $companyId)
            ->where('providers.status', 'active')
            ->where(self::TABLE.'.status', TestStatus::Published->value)
            ->select(self::TABLE.'.*');

        return $this->page($builder, $search, $request, self::TABLE.'.title', 'asc');
    }

    public function pagePublishedBatteries(?string $search, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE)
            ->join('assessment_providers as providers', 'providers.id', '=', self::TABLE.'.provider_id')
            ->where('providers.status', 'active')
            ->where(self::TABLE.'.status', TestStatus::Published->value)
            ->select(self::TABLE.'.*');

        return $this->page($builder, $search, $request, self::TABLE.'.title', 'asc');
    }

    public function batteryIsUsableBy(int $companyId, int $testId): bool
    {
        return $this->connection->table(self::TABLE)
            ->join('assessment_company_batteries as grants', 'grants.test_id', '=', self::TABLE.'.id')
            ->join('assessment_providers as providers', 'providers.id', '=', self::TABLE.'.provider_id')
            ->where(self::TABLE.'.id', $testId)
            ->where('grants.company_id', $companyId)
            ->where('providers.status', 'active')
            ->where(self::TABLE.'.status', TestStatus::Published->value)
            ->exists();
    }

    /** @return Page<Test> */
    private function page(Builder $builder, ?string $search, PageRequest $request, string $orderColumn, string $direction): Page
    {
        if ($search !== null && $search !== '') {
            $builder->where(fn (Builder $match) => TextSearch::contains($match, self::TABLE.'.title', $search));
        }

        $total = (clone $builder)->count();
        $rows = $builder->orderBy($orderColumn, $direction)->orderBy(self::TABLE.'.id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): Test => $this->toTest($row), $rows->all()), $total, $request);
    }

    private function toTest(stdClass $row): Test
    {
        $owner = $row->provider_id !== null
            ? TestOwner::provider((int) $row->provider_id)
            : ($row->company_id !== null ? TestOwner::company((int) $row->company_id) : TestOwner::library());

        /** @var list<string> $capabilities */
        $capabilities = json_decode((string) $row->measured_capabilities, true, 512, JSON_THROW_ON_ERROR);

        return new Test(
            (int) $row->id,
            $owner,
            (string) $row->title,
            $row->description,
            $row->instructions,
            $capabilities,
            TestStatus::from($row->status),
            $row->created_by_user_id === null ? null : (int) $row->created_by_user_id,
            $row->created_by_admin_id === null ? null : (int) $row->created_by_admin_id,
            $row->published_at === null ? null : new DateTimeImmutable((string) $row->published_at, new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }
}
