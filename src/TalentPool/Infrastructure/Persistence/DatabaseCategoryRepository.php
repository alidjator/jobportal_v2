<?php

namespace Src\TalentPool\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\TalentPool\Domain\Category;
use Src\TalentPool\Domain\CategoryRepository;
use stdClass;

final readonly class DatabaseCategoryRepository implements CategoryRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(Category $category): Category
    {
        try {
            $id = (int) $this->connection->table('talentpool_categories')->insertGetId([
                'company_id' => $category->companyId,
                'name' => $category->name,
                'created_at' => $this->format($category->createdAt),
                'updated_at' => $this->format($category->createdAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('Your company already has a category with this name.', 'category-name-taken');
        }

        return new Category($category->companyId, $category->name, $category->createdAt, $id);
    }

    public function find(int $companyId, int $id): ?Category
    {
        $row = $this->connection->table('talentpool_categories')->where('company_id', $companyId)->where('id', $id)->first();

        return $row === null ? null : $this->toCategory($row);
    }

    public function ofCompany(int $companyId): array
    {
        return array_map(
            fn (stdClass $row): Category => $this->toCategory($row),
            $this->connection->table('talentpool_categories')->where('company_id', $companyId)->orderByRaw('lower("name")')->orderBy('id')->get()->all(),
        );
    }

    public function count(int $companyId): int
    {
        return $this->connection->table('talentpool_categories')->where('company_id', $companyId)->count();
    }

    public function nameTaken(int $companyId, string $name, ?int $exceptId = null): bool
    {
        $query = $this->connection->table('talentpool_categories')->where('company_id', $companyId)->whereRaw('lower("name") = ?', [mb_strtolower($name)]);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function rename(int $companyId, int $id, string $name): void
    {
        try {
            $this->connection->table('talentpool_categories')->where('company_id', $companyId)->where('id', $id)->update([
                'name' => $name,
                'updated_at' => $this->format(new DateTimeImmutable),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('Your company already has a category with this name.', 'category-name-taken');
        }
    }

    public function delete(int $companyId, int $id): void
    {
        $this->connection->table('talentpool_categories')->where('company_id', $companyId)->where('id', $id)->delete();
    }

    private function toCategory(stdClass $row): Category
    {
        return new Category((int) $row->company_id, (string) $row->name, new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')), (int) $row->id);
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
