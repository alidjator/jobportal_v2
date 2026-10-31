<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\Section;

/** A section of a test with the sample questions it holds. */
final readonly class PracticeSection
{
    /** @param  list<Question>  $questions */
    public function __construct(public Section $section, public array $questions) {}
}
