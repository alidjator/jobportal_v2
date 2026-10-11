<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The author removes a question. */
final readonly class DeleteQuestion
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private QuestionRepository $questions,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-archived` or `test-locked`
     */
    public function execute(Actor $actor, int $questionId): void
    {
        $author = $this->access->author($actor);

        $this->transactions->run(function () use ($author, $questionId): void {
            $question = $this->questions->find($questionId) ?? throw EntityNotFound::of('Question', $questionId);
            $section = $this->sections->find($question->sectionId) ?? throw EntityNotFound::of('Section', $question->sectionId);
            $this->access->editableTest($author, $section->testId);

            $this->questions->delete($questionId);
        });
    }
}
