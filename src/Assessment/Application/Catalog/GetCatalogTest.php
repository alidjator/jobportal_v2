<?php

namespace Src\Assessment\Application\Catalog;

use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Domain\TestStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A company member reads a library test or a granted battery in the catalogue. A test the company may not use is the same 404 as one that does not
 * exist.
 */
final readonly class GetCatalogTest
{
    public function __construct(
        private CatalogAccess $access,
        private TestRepository $tests,
        private SectionRepository $sections,
        private QuestionRepository $questions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId): CatalogEntry
    {
        $companyId = $this->access->companyId($actor);
        $test = $this->tests->find($testId);

        $usable = $test !== null && $test->status() === TestStatus::Published && (
            $test->owner->isLibrary()
            || ($test->kind() === TestKind::Psychological && $this->access->psychologicalEnabled($companyId) && $this->tests->batteryIsUsableBy($companyId, $testId))
        );

        if (! $usable) {
            throw EntityNotFound::of('Test', $testId);
        }

        $sections = $this->sections->ofTest($testId);

        return new CatalogEntry(
            $test,
            count($sections),
            array_sum(array_map(static fn ($section): int => $section->durationSeconds, $sections)),
            $this->questions->samplesOfTest($testId),
        );
    }
}
