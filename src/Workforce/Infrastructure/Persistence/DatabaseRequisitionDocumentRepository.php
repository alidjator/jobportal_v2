<?php

namespace Src\Workforce\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Clock;
use Src\Workforce\Domain\RequisitionDocument;
use Src\Workforce\Domain\RequisitionDocumentRepository;
use stdClass;

final readonly class DatabaseRequisitionDocumentRepository implements RequisitionDocumentRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function create(RequisitionDocument $document): RequisitionDocument
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table('workforce_requisition_documents')->insertGetId([
            'requisition_id' => $document->requisitionId, 'name' => $document->name, 'path' => $document->path, 'content_type' => $document->contentType,
            'size_bytes' => $document->sizeBytes, 'uploaded_by_id' => $document->uploadedById, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->find($document->requisitionId, $id) ?? throw new \LogicException('The document was just stored.');
    }

    public function find(int $requisitionId, int $id): ?RequisitionDocument
    {
        $row = $this->connection->table('workforce_requisition_documents')->where('requisition_id', $requisitionId)->where('id', $id)->first();

        return $row === null ? null : $this->toDocument($row);
    }

    public function of(int $requisitionId): array
    {
        return array_map($this->toDocument(...), $this->connection->table('workforce_requisition_documents')->where('requisition_id', $requisitionId)->orderBy('id')->get()->all());
    }

    public function count(int $requisitionId): int
    {
        return $this->connection->table('workforce_requisition_documents')->where('requisition_id', $requisitionId)->count();
    }

    public function delete(int $requisitionId, int $id): void
    {
        $this->connection->table('workforce_requisition_documents')->where('requisition_id', $requisitionId)->where('id', $id)->delete();
    }

    private function toDocument(stdClass $row): RequisitionDocument
    {
        return new RequisitionDocument(
            (int) $row->id, (int) $row->requisition_id, $row->name, $row->path, $row->content_type, (int) $row->size_bytes, (int) $row->uploaded_by_id, new DateTimeImmutable($row->created_at),
        );
    }
}
