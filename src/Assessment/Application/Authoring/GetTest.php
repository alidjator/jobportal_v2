<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionDraw;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\SectionFormat;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The author reads one of their tests with its sections. */
final readonly class GetTest
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private QuestionRepository $questions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId): TestDetails
    {
        $test = $this->access->ownedTest($this->access->author($actor), $testId);

        $sections = $this->sections->ofTest($testId);
        $counts = $this->questions->countsBySection($testId);
        $others = $this->questions->countsBySection($testId, true);
        $drawn = [];

        foreach ($sections as $section) {
            $drawn[(int) $section->id] = $section->format !== SectionFormat::SpeedColumns
                && QuestionDraw::drawsSamples($counts[(int) $section->id] ?? 0, $others[(int) $section->id] ?? 0, $section->drawCount);
        }

        return new TestDetails($test, $sections, $counts, $drawn);
    }
}
