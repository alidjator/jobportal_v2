<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\DocumentType;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\TranslatedText;
use stdClass;

final readonly class DatabaseDocumentTypeRepository implements DocumentTypeRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function all(): array
    {
        return array_map(
            fn (stdClass $row): DocumentType => $this->toType($row),
            $this->connection->table('company_document_types')->orderBy('sort_order')->orderBy('id')->get()->all(),
        );
    }

    public function find(int $id): ?DocumentType
    {
        $row = $this->connection->table('company_document_types')->where('id', $id)->first();

        return $row === null ? null : $this->toType($row);
    }

    public function lock(int $id): void
    {
        $this->connection->table('company_document_types')->where('id', $id)->lockForUpdate()->first(['id']);
    }

    public function slugExists(string $slug): bool
    {
        return $this->connection->table('company_document_types')->where('slug', $slug)->exists();
    }

    public function create(DocumentType $type): DocumentType
    {
        $time = $this->clock->now()->format(self::TIME_FORMAT);

        $id = $this->connection->table('company_document_types')->insertGetId([
            'slug' => $type->slug,
            'name' => json_encode($type->name->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'is_required' => $type->isRequired,
            'is_active' => $type->isActive,
            'sort_order' => $type->sortOrder,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        return new DocumentType((int) $id, $type->slug, $type->name, $type->isRequired, $type->isActive, $type->sortOrder);
    }

    public function save(DocumentType $type): void
    {
        $this->connection->table('company_document_types')->where('id', $type->id)->update([
            'name' => json_encode($type->name->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'is_required' => $type->isRequired,
            'is_active' => $type->isActive,
            'sort_order' => $type->sortOrder,
            'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);
    }

    private function toType(stdClass $row): DocumentType
    {
        /** @var array<array-key, mixed> $name */
        $name = json_decode((string) $row->name, true, flags: JSON_THROW_ON_ERROR);

        return new DocumentType(
            (int) $row->id,
            (string) $row->slug,
            TranslatedText::fromArray($name),
            (bool) $row->is_required,
            (bool) $row->is_active,
            (int) $row->sort_order,
        );
    }
}
