<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

interface ReportTemplateRepository
{
    public function bodyOf(int $testId): ?string;

    public function save(int $testId, string $body, DateTimeImmutable $now): void;

    /** The text that gives the candidate's explanation of an automatically issued report, or null while the battery has none. */
    public function explanationTemplateOf(int $testId): ?string;

    public function saveExplanationTemplate(int $testId, string $body, DateTimeImmutable $now): void;
}
