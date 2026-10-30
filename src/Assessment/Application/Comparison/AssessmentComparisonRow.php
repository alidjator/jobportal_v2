<?php

namespace Src\Assessment\Application\Comparison;

use Src\Assessment\Domain\SubmittedAssessment;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Recruitment\RosterEntry;

/**
 * One application of the comparison with its latest submitted test. `status` is `scored` or `pending_scoring` for a skill test, `awaiting_psychologist_review` or `signed` for a
 * battery. `sections` (skill) and `scales` (battery, only once signed) are null for a row whose test is not the one of the columns.
 */
final readonly class AssessmentComparisonRow
{
    /**
     * @param  list<array{title: string, percentage: float|null}>|null  $sections
     * @param  list<array{scale: string, band: string|null}>|null  $scales
     */
    public function __construct(
        public RosterEntry $application,
        public SubmittedAssessment $assessment,
        public string $testName,
        public TestKind $kind,
        public string $status,
        public ?array $sections,
        public ?array $scales,
    ) {}
}
