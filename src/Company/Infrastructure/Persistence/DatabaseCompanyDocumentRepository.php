<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\CompanyDocument;
use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\DocumentStatus;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseCompanyDocumentRepository implements CompanyDocumentRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $companyId, int $documentTypeId): ?CompanyDocument
    {
        $row = $this->connection->table('company_documents')
            ->where('company_id', $companyId)
            ->where('document_type_id', $documentTypeId)
            ->first();

        return $row === null ? null : $this->toDocument($row);
    }

    public function ofCompany(int $companyId): array
    {
        return array_map(
            fn (stdClass $row): CompanyDocument => $this->toDocument($row),
            $this->connection->table('company_documents')->where('company_id', $companyId)->get()->all(),
        );
    }

    public function create(CompanyDocument $document): CompanyDocument
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        $id = $this->connection->table('company_documents')->insertGetId([
            ...$this->columns($document),
            'company_id' => $document->companyId,
            'document_type_id' => $document->documentTypeId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new CompanyDocument(
            (int) $id, $document->companyId, $document->documentTypeId, $document->status, $document->filePath, $document->originalName,
            $document->mimeType, $document->sizeBytes, $document->rejectionReason, $document->rejectionNote, $document->submittedAt,
            $document->reviewedAt, $document->reviewedByAdminId,
        );
    }

    public function save(CompanyDocument $document): void
    {
        $this->connection->table('company_documents')->where('id', $document->id)->update([
            ...$this->columns($document),
            'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);
    }

    public function delete(int $id): void
    {
        $this->connection->table('company_documents')->where('id', $id)->delete();
    }

    /** @return array<string, mixed> */
    private function columns(CompanyDocument $document): array
    {
        return [
            'status' => $document->status->value,
            'file_path' => $document->filePath,
            'original_name' => $document->originalName,
            'mime_type' => $document->mimeType,
            'size_bytes' => $document->sizeBytes,
            'rejection_reason' => $document->rejectionReason,
            'rejection_note' => $document->rejectionNote,
            'submitted_at' => $document->submittedAt->format(self::TIME_FORMAT),
            'reviewed_at' => $document->reviewedAt?->format(self::TIME_FORMAT),
            'reviewed_by_admin_id' => $document->reviewedByAdminId,
        ];
    }

    private function toDocument(stdClass $row): CompanyDocument
    {
        return new CompanyDocument(
            (int) $row->id,
            (int) $row->company_id,
            (int) $row->document_type_id,
            DocumentStatus::from((string) $row->status),
            (string) $row->file_path,
            (string) $row->original_name,
            (string) $row->mime_type,
            (int) $row->size_bytes,
            $row->rejection_reason === null ? null : (string) $row->rejection_reason,
            $row->rejection_note === null ? null : (string) $row->rejection_note,
            $this->time((string) $row->submitted_at),
            $row->reviewed_at === null ? null : $this->time((string) $row->reviewed_at),
            $row->reviewed_by_admin_id === null ? null : (int) $row->reviewed_by_admin_id,
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
