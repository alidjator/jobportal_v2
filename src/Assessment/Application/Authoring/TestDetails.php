<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\Test;

/** A test with its sections, the number of questions in each and the sections that draw their sample questions for want of others. */
final readonly class TestDetails
{
    /**
     * @param  list<Section>  $sections
     * @param  array<int, int>  $questionCounts  section id => questions
     * @param  array<int, bool>  $samplesDrawn  section id => true while the section draws its samples (a section missing from it does not)
     */
    public function __construct(
        public Test $test,
        public array $sections,
        public array $questionCounts,
        public array $samplesDrawn = [],
    ) {}
}
