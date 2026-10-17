<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;

/** One application of a review request. `$active` is false once it became final (nothing is awaited then). `$locked` hides the others' answers from a reviewer who has not answered it. */
final readonly class ReviewItemView
{
    /**
     * @param  list<ReviewAnswerView>  $answers  one per reviewer
     * @param  ?array<string, mixed>  $card  the compact card of the candidate; null in the lists
     */
    public function __construct(
        public int $applicationId,
        public ?string $candidateName,
        public int $stageId,
        public StageType $stageType,
        public ?string $stageName,
        public bool $active,
        public bool $locked,
        public array $answers,
        public ?array $card,
    ) {}
}
