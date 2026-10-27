<?php

namespace Src\Recruitment\Application;

/**
 * Which presets to list: the company's, optionally those whose name holds a piece of text (any letter case) and those with this level hint. With
 * `$firstForLevelId` the presets with that level hint come first.
 */
final readonly class ScreeningPresetListQuery
{
    public function __construct(public int $companyId, public ?string $search, public ?int $jobLevelId, public ?int $firstForLevelId = null) {}
}
