<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\OfferLetterUpload;
use Src\Recruitment\Domain\OfferLetterUploadRepository;
use stdClass;

final readonly class DatabaseOfferLetterUploadRepository implements OfferLetterUploadRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(OfferLetterUpload $upload): OfferLetterUpload
    {
        $id = (int) $this->connection->table('recruitment_offer_letter_uploads')->insertGetId([
            'application_id' => $upload->applicationId,
            'uploaded_by' => $upload->uploadedBy,
            'path' => $upload->path,
            'size_bytes' => $upload->sizeBytes,
            'sha256' => $upload->sha256,
            'created_at' => $upload->createdAt->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT),
        ]);

        return new OfferLetterUpload($upload->applicationId, $upload->uploadedBy, $upload->path, $upload->sizeBytes, $upload->sha256, $upload->createdAt, $id);
    }

    public function find(int $uploadId): ?OfferLetterUpload
    {
        $row = $this->connection->table('recruitment_offer_letter_uploads')->where('id', $uploadId)->first();

        return $row === null ? null : $this->toUpload($row);
    }

    public function delete(int $uploadId): void
    {
        $this->connection->table('recruitment_offer_letter_uploads')->where('id', $uploadId)->delete();
    }

    public function createdBefore(DateTimeImmutable $cutoff, int $limit): array
    {
        return $this->connection->table('recruitment_offer_letter_uploads')
            ->where('created_at', '<', $cutoff->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (stdClass $row): OfferLetterUpload => $this->toUpload($row))
            ->all();
    }

    private function toUpload(stdClass $row): OfferLetterUpload
    {
        return new OfferLetterUpload(
            (int) $row->application_id,
            (int) $row->uploaded_by,
            (string) $row->path,
            (int) $row->size_bytes,
            (string) $row->sha256,
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }
}
