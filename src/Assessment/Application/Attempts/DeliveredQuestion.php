<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\Answer;
use Src\Assessment\Domain\Drawing;
use Src\Assessment\Domain\Question;

final readonly class DeliveredQuestion
{
    public function __construct(
        public int $index,
        public Question $question,
        public ?Answer $answer,
        public ?Drawing $drawing,
    ) {}
}
