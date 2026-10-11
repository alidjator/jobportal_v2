<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Assessment\Domain\Provider;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\ProviderStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Infrastructure\Persistence\TextSearch;
use stdClass;

final readonly class DatabaseProviderRepository implements ProviderRepository
{
    private const string TABLE = 'assessment_providers';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function findById(int $id, bool $forUpdate = false): ?Provider
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toProvider($row);
    }

    public function save(Provider $provider): Provider
    {
        $now = $this->clock->now();

        if ($provider->id !== null) {
            $this->connection->table(self::TABLE)->where('id', $provider->id)->update([
                'code' => $provider->code(),
                'name' => $provider->name(),
                'legal_name' => $provider->legalName(),
                'status' => $provider->status()->value,
                'updated_at' => $now,
            ]);

            return $provider;
        }

        $id = $this->connection->table(self::TABLE)->insertGetId([
            'code' => $provider->code(),
            'name' => $provider->name(),
            'legal_name' => $provider->legalName(),
            'status' => $provider->status()->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new Provider((int) $id, $provider->code(), $provider->name(), $provider->legalName(), $provider->status(), $now);
    }

    public function nameIsTaken(string $name, ?int $exceptId = null): bool
    {
        $query = $this->connection->table(self::TABLE)->whereRaw('lower(name) = lower(?)', [$name]);

        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function codeIsTaken(string $code, ?int $exceptId = null): bool
    {
        $query = $this->connection->table(self::TABLE)->where('code', $code);

        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function page(?string $search, ?ProviderStatus $status, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE);

        if ($search !== null && $search !== '') {
            $builder->where(fn (Builder $match) => TextSearch::contains(TextSearch::contains($match, 'name', $search), 'legal_name', $search, 'or'));
        }

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        $total = (clone $builder)->count();
        $rows = $builder->orderBy('name')->orderBy('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): Provider => $this->toProvider($row), $rows->all()), $total, $request);
    }

    private function toProvider(stdClass $row): Provider
    {
        return new Provider(
            (int) $row->id,
            (string) $row->code,
            (string) $row->name,
            (string) $row->legal_name,
            ProviderStatus::from($row->status),
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }
}
