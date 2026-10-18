<?php

namespace Src\Platform\Application\EmailSuppression;

use DateTimeImmutable;

final readonly class EmailSuppression
{
    public function __construct(
        public int $id,
        public string $email,
        public EmailSuppressionReason $reason,
        public ?string $source,
        public ?string $details,
        public DateTimeImmutable $createdAt,
    ) {}
}
