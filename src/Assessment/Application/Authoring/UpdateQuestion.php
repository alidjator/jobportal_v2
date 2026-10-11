<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The author replaces a question's content and options. */
final readonly class UpdateQuestion
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private QuestionRepository $questions,
        private QuestionFactory $factory,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-question`
     * @throws StateConflict `test-archived` or `test-locked`
     */
    public function execute(Actor $actor, int $questionId, QuestionDraft $draft): Question
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $questionId, $draft): Question {
            $current = $this->questions->find($questionId) ?? throw EntityNotFound::of('Question', $questionId);
            $section = $this->sections->find($current->sectionId) ?? throw EntityNotFound::of('Section', $current->sectionId);
            $test = $this->access->editableTest($author, $section->testId);

            $question = $this->factory->build($test, $section, $draft, $questionId, $current->position);
            $this->questions->update($question);

            return $this->questions->find($questionId) ?? throw EntityNotFound::of('Question', $questionId);
        });
    }
}
