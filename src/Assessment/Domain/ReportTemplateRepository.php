<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

interface ReportTemplateRepository
{
    public function bodyOf(int $testId): ?string;

    public function save(int $testId, string $body, DateTimeImmutable $now): void;
}
