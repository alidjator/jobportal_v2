<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/** The raw data of one psychological examination kept for the institution after the erasure of the candidate's account, until `retainUntil` (`docs/design/phase-8-provider-institutions.md` section 7). */
final readonly class ArchivedExamination
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{n: int, kind: string, name: string, path: string, sha256: string}>  $files  the copies kept on the private disk
     */
    public function __construct(
        public ?int $id,
        public int $attemptId,
        public int $providerId,
        public ?string $reportNumber,
        public DateTimeImmutable $archivedAt,
        public DateTimeImmutable $retainUntil,
        public array $payload,
        public array $files,
    ) {}
}
