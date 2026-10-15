<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

interface DataExportRepository
{
    /** The newest request of the account, whatever its state. */
    public function latestOf(int $userId): ?DataExport;

    public function find(int $id): ?DataExport;

    /** Starts a new request in the state `building`. */
    public function start(int $userId, DateTimeImmutable $now): DataExport;

    public function markReady(int $id, string $path, int $sizeBytes, DateTimeImmutable $readyAt, DateTimeImmutable $expiresAt): void;

    public function markFailed(int $id, string $error, DateTimeImmutable $at): void;

    /**
     * The ready exports that have expired (their file still has to be deleted) and the builds that never finished, oldest first; nothing is changed.
     *
     * @return list<DataExport>
     */
    public function due(DateTimeImmutable $now, int $limit): array;

    /** Moves a ready export to `expired` and forgets its file. */
    public function markExpired(int $id, DateTimeImmutable $at): void;

    /** Deletes the requests of the account (erasure); the files are deleted by the caller first. @return list<DataExport> the requests that were there */
    public function ofUser(int $userId): array;

    public function deleteOfUser(int $userId): void;
}
