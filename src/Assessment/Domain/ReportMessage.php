<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/** One question or answer about a signed report (`docs/design/phase-8-provider-institutions.md` section 6): plain text, not part of the report. */
final readonly class ReportMessage
{
    /** The longest message, in characters. */
    public const int BODY_MAX = 2000;

    public function __construct(
        public ?int $id,
        public int $reportId,
        public ReportMessageAuthor $author,
        public int $authorUserId,
        public string $body,
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
