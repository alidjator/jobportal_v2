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

/** The author puts the questions of a section in a new order; the list must hold every question once. */
final readonly class ReorderQuestions
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private QuestionRepository $questions,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<int>  $orderedIds
     * @return list<Question>
     *
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-archived` or `test-locked`
     * @throws BusinessRuleViolation `invalid-order`
     */
    public function execute(Actor $actor, int $sectionId, array $orderedIds): array
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $sectionId, $orderedIds): array {
            $section = $this->sections->find($sectionId) ?? throw EntityNotFound::of('Section', $sectionId);
            $this->access->editableTest($author, $section->testId);

            $current = array_map(static fn (Question $question): int => (int) $question->id, $this->questions->ofSection($sectionId));

            if (count($orderedIds) !== count($current) || array_diff($orderedIds, $current) !== [] || count(array_unique($orderedIds)) !== count($orderedIds)) {
                throw new BusinessRuleViolation('The list must hold every question of the section exactly once.', 'invalid-order', extensions: ['errors' => ['ids' => ['The list must hold every question of the section exactly once.']]]);
            }

            $this->questions->reorder($sectionId, $orderedIds);

            return $this->questions->ofSection($sectionId);
        });
    }
}
