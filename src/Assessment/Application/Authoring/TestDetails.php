<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\Test;

/** A test with its sections and the number of questions in each. */
final readonly class TestDetails
{
    /**
     * @param  list<Section>  $sections
     * @param  array<int, int>  $questionCounts  section id => questions
     */
    public function __construct(
        public Test $test,
        public array $sections,
        public array $questionCounts,
    ) {}
}
