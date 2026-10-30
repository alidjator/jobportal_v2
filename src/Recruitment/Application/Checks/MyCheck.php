<?php

namespace Src\Recruitment\Application\Checks;

use DateTimeImmutable;
use Src\Recruitment\Domain\CheckKind;
use Src\Recruitment\Domain\CheckStatus;

/** A check as the candidate sees it: the name, the schedule and the instructions, never the outcome, the team note or the file. After `completed` it reads "Selesai". */
final readonly class MyCheck
{
    public function __construct(
        public int $id,
        public string $name,
        public CheckKind $kind,
        public CheckStatus $status,
        public ?DateTimeImmutable $scheduledAt,
        public ?string $place,
        public ?string $vendor,
        public ?string $instructions,
        public ?string $noteToCandidate,
        public bool $consentRequired,
        public ?DateTimeImmutable $consentedAt,
        public ?DateTimeImmutable $consentRefusedAt,
    ) {}
}
