<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface OfferLetterUploadRepository
{
    /** Saves a new upload and returns it with its id. */
    public function add(OfferLetterUpload $upload): OfferLetterUpload;

    public function find(int $uploadId): ?OfferLetterUpload;

    public function delete(int $uploadId): void;

    /**
     * Uploads made before `$cutoff`, oldest first, at most `$limit`.
     *
     * @return list<OfferLetterUpload>
     */
    public function createdBefore(DateTimeImmutable $cutoff, int $limit): array;
}
