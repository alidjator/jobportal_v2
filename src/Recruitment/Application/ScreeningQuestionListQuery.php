<?php

namespace Src\Recruitment\Application;

/** Which library questions to list: the company's, archived or not, and optionally those whose text holds a piece of text (any letter case). */
final readonly class ScreeningQuestionListQuery
{
    public function __construct(public int $companyId, public bool $archived, public ?string $search) {}
}
