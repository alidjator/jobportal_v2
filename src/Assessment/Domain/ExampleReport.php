<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/** The example report a provider attached to a battery: a PDF about a fictitious candidate that a company may download from the catalogue before it asks for the battery. */
final readonly class ExampleReport
{
    public function __construct(
        public string $path,
        public string $originalName,
        public int $sizeBytes,
        public DateTimeImmutable $uploadedAt,
    ) {}
}
