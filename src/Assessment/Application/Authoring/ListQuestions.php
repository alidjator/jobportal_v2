<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The author reads the questions of a section of their own test, with every option and the correct answers. */
final readonly class ListQuestions
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private QuestionRepository $questions,
    ) {}

    /**
     * @return list<Question>
     *
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $sectionId): array
    {
        $author = $this->access->author($actor);
        $section = $this->sections->find($sectionId) ?? throw EntityNotFound::of('Section', $sectionId);
        $this->access->ownedTest($author, $section->testId);

        return $this->questions->ofSection($sectionId);
    }
}
