<?php

namespace Src\Platform\Application\Email;

use DateTimeImmutable;

/** An admin's replacement of the default subject and body of a template in one language. */
final readonly class EmailTemplateOverride
{
    public function __construct(
        public string $templateKey,
        public string $locale,
        public string $subject,
        public string $body,
        public DateTimeImmutable $updatedAt,
        public ?int $updatedByAdminId,
    ) {}
}
