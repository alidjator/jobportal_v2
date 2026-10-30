<?php

namespace Src\Assessment\Application\Comparison;

use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Pagination\Page;

/**
 * The compared test (null when no application has a submitted test) and its columns: the titles of its sections for a skill test, its scales for a battery.
 */
final readonly class AssessmentComparison
{
    /**
     * @param  array{id: int, name: string, kind: TestKind}|null  $test
     * @param  list<string>  $columns
     * @param  Page<AssessmentComparisonRow>  $rows
     */
    public function __construct(public ?array $test, public array $columns, public Page $rows) {}
}
