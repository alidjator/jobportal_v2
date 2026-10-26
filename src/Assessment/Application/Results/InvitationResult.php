<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Application\Delivery\InvitationView;
use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptResult;
use Src\Assessment\Domain\Report;

/**
 * What the company's team sees of a test: the invitation, the attempt and its result. A psychological test shows its scores only once a psychologist signed
 * the report, so `scales` is empty before that and `report` is the draft's state (never its text) until then.
 */
final readonly class InvitationResult
{
    /**
     * @param  list<array{scale: string, raw: float, standard: float|null, band: string|null, norm_group: array{age_min: int|null, age_max: int|null, education_rank_min: int|null, education_rank_max: int|null}|null}>  $scales
     */
    public function __construct(
        public InvitationView $invitation,
        public ?Attempt $attempt,
        public ?AttemptResult $result,
        public ?Report $report,
        public array $scales,
    ) {}
}
