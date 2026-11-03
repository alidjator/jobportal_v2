<?php

namespace Src\Assessment\Domain;

/**
 * The raw data of one psychological attempt as it is live now, ready to be archived: the JSON payload (the rows, with the files named by their index) and the files it points to.
 * `docs/design/phase-8-provider-institutions.md` section 7.
 */
final readonly class ExaminationSnapshot
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{kind: string, path: string, name: string}>  $sources  the live files, by the index the payload uses
     */
    public function __construct(
        public int $attemptId,
        public int $providerId,
        public ?string $reportNumber,
        public array $payload,
        public array $sources,
    ) {}
}
