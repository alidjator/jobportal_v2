<?php

namespace Src\ReferenceData\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\ReferenceData\Application\Masters\FieldType;
use Src\ReferenceData\Application\Masters\MasterDefinition;
use Src\ReferenceData\Application\Masters\MasterItem;
use Src\ReferenceData\Application\Masters\MasterQuery;
use Src\ReferenceData\Application\Masters\MasterRepository;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Domain\TranslatedText;
use stdClass;

/**
 * Reads and writes any master table from its definition. Table and column names come only from `MasterCatalog` (code), never
 * from a request; request values are always bound parameters. Names are JSON (`{"id": ..., "en": ...}`) searched and sorted directly.
 */
final readonly class DatabaseMasterRepository implements MasterRepository
{
    private const string TEXT_COLLATION = 'utf8mb4_unicode_ci';

    public function __construct(private ConnectionInterface $connection) {}

    public function all(MasterDefinition $definition, MasterQuery $query): array
    {
        return $this->items($definition, $this->filtered($definition, $query)->get()->all());
    }

    public function page(MasterDefinition $definition, MasterQuery $query): Page
    {
        $request = $query->page ?? throw new \LogicException('A paginated read needs a page request.');
        $builder = $this->filtered($definition, $query);

        $total = (clone $builder)->reorder()->count();
        $rows = $builder->offset($request->offset())->limit($request->perPage)->get()->all();

        return new Page($this->items($definition, $rows), $total, $request);
    }

    public function find(MasterDefinition $definition, int $id): ?MasterItem
    {
        $row = $this->select($definition)->where('id', $id)->first();

        return $row === null ? null : $this->toItem($definition, $row);
    }

    public function slugExists(MasterDefinition $definition, string $slug): bool
    {
        return $this->connection->table($definition->table)->where('slug', $slug)->exists();
    }

    public function extraValueTaken(MasterDefinition $definition, string $column, string $value, ?int $exceptId): bool
    {
        $this->assertExtra($definition, $column);

        return $this->connection->table($definition->table)
            ->where($column, $value)
            ->when($exceptId !== null, fn (Builder $query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function create(MasterDefinition $definition, string $slug, TranslatedText $name, int $sortOrder, bool $isActive, array $extras): MasterItem
    {
        $id = $this->connection->table($definition->table)->insertGetId([
            'name' => $this->encode($name),
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            ...$this->extraValues($definition, $extras),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->find($definition, (int) $id) ?? throw new \LogicException('The created row cannot be read back.');
    }

    public function update(MasterDefinition $definition, int $id, TranslatedText $name, int $sortOrder, bool $isActive, array $extras): MasterItem
    {
        $this->connection->table($definition->table)->where('id', $id)->update([
            'name' => $this->encode($name),
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            ...$this->extraValues($definition, $extras),
            'updated_at' => now(),
        ]);

        return $this->find($definition, $id) ?? throw new \LogicException('The updated row cannot be read back.');
    }

    private function select(MasterDefinition $definition): Builder
    {
        $columns = ['id', 'slug', 'name', 'sort_order', 'is_active', ...array_map(fn ($field): string => $field->name, $definition->extras)];

        return $this->connection->table($definition->table)->select($columns);
    }

    private function filtered(MasterDefinition $definition, MasterQuery $query): Builder
    {
        $builder = $this->select($definition);

        foreach ($definition->fixedColumns as $column => $value) {
            $builder->where($column, $value);
        }

        if ($query->onlyActive) {
            $builder->where('is_active', true);
        }

        foreach ($query->filters as $column => $value) {
            $this->assertExtra($definition, $column);
            $builder->where($column, $value);
        }

        if ($query->search !== null && $query->search !== '') {
            $like = '%'.addcslashes($query->search, '%_\\').'%';
            // The JSON column is compared as binary text, so the collation is named to make the search ignore case and accents.
            $builder->where(fn (Builder $search) => $search
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) COLLATE ".self::TEXT_COLLATION.' LIKE ?', [$like])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.id')) COLLATE ".self::TEXT_COLLATION.' LIKE ?', [$like]));
        }

        return $this->ordered($builder, $query);
    }

    private function ordered(Builder $builder, MasterQuery $query): Builder
    {
        $language = in_array($query->language, TranslatedText::LANGUAGES, true) ? $query->language : TranslatedText::LANGUAGES[0];
        $name = "JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$language}')) COLLATE ".self::TEXT_COLLATION;

        return match ($query->sort) {
            MasterQuery::SORT_NAME => $builder->orderByRaw("{$name} asc")->orderBy('id'),
            MasterQuery::SORT_NAME_DESCENDING => $builder->orderByRaw("{$name} desc")->orderBy('id'),
            default => $builder->orderBy('sort_order')->orderByRaw("{$name} asc")->orderBy('id'),
        };
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<MasterItem>
     */
    private function items(MasterDefinition $definition, array $rows): array
    {
        return array_map(fn (stdClass $row): MasterItem => $this->toItem($definition, $row), $rows);
    }

    private function toItem(MasterDefinition $definition, stdClass $row): MasterItem
    {
        $extras = [];

        foreach ($definition->extras as $field) {
            $value = $row->{$field->name};
            $extras[$field->name] = $value === null ? null : ($field->type === FieldType::Integer ? (int) $value : (string) $value);
        }

        return new MasterItem(
            (int) $row->id,
            (string) $row->slug,
            TranslatedText::fromArray(json_decode((string) $row->name, true, 512, JSON_THROW_ON_ERROR)),
            (int) $row->sort_order,
            (bool) $row->is_active,
            $extras,
        );
    }

    /**
     * @param  array<string, int|string|null>  $extras
     * @return array<string, int|string|null>
     */
    private function extraValues(MasterDefinition $definition, array $extras): array
    {
        $values = [];

        foreach ($definition->extras as $field) {
            $values[$field->name] = $extras[$field->name] ?? null;
        }

        return $values;
    }

    private function encode(TranslatedText $name): string
    {
        return json_encode($name->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function assertExtra(MasterDefinition $definition, string $column): void
    {
        if ($definition->extra($column) === null) {
            throw new \LogicException("{$column} is not a column of {$definition->key}.");
        }
    }
}
