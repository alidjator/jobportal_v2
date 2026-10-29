<?php

namespace Src\Shared\Application\Workforce;

/** A requisition a job may still be opened for: approved or in recruitment, with the hires its jobs have not taken yet. */
final readonly class OpenRequisition
{
    public function __construct(
        public int $id,
        public string $positionTitle,
        public int $hires,
        public int $remaining,
        public string $targetJoinDate,
    ) {}
}
