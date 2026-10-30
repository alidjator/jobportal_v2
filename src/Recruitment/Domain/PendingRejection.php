<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/**
 * A rejection that waits its undo window (`docs/design/phase-8-rejections.md` section 2). The application stays in `stageId` until `commitAt`; `batchId` ties the rows of one bulk action
 * so the whole action can be undone together.
 */
final readonly class PendingRejection
{
    public function __construct(
        public int $applicationId,
        public int $stageId,
        public RejectionReason $reason,
        public ?string $note,
        public int $requestedByUserId,
        public DateTimeImmutable $requestedAt,
        public DateTimeImmutable $commitAt,
        public ?string $batchId = null,
        public ?int $id = null,
    ) {}
}
