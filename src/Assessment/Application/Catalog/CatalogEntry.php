<?php

namespace Src\Assessment\Application\Catalog;

use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\Test;

/**
 * What a company sees of a test it may send: the description, the capabilities, the size and the sample questions the author chose. Never the
 * question bank. `hasExampleReport` says whether the provider attached an example report that the company can download.
 */
final readonly class CatalogEntry
{
    /**
     * @param  list<Question>  $samples
     */
    public function __construct(
        public Test $test,
        public int $sectionCount,
        public int $totalSeconds,
        public array $samples,
        public bool $hasExampleReport = false,
    ) {}
}
