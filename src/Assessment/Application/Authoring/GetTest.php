<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionRepository;
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

        return new TestDetails($test, $this->sections->ofTest($testId), $this->questions->countsBySection($testId));
    }
}
