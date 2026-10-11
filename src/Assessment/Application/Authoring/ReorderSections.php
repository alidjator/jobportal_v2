<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The author puts the sections of a test in a new order; the list must hold every section once. */
final readonly class ReorderSections
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<int>  $orderedIds
     * @return list<Section>
     *
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-archived` or `test-locked`
     * @throws BusinessRuleViolation `invalid-order`
     */
    public function execute(Actor $actor, int $testId, array $orderedIds): array
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $testId, $orderedIds): array {
            $this->access->editableTest($author, $testId);

            $current = array_map(static fn ($section): int => (int) $section->id, $this->sections->ofTest($testId));

            if (count($orderedIds) !== count($current) || array_diff($orderedIds, $current) !== [] || count(array_unique($orderedIds)) !== count($orderedIds)) {
                throw new BusinessRuleViolation('The list must hold every section of the test exactly once.', 'invalid-order', extensions: ['errors' => ['ids' => ['The list must hold every section of the test exactly once.']]]);
            }

            $this->sections->reorder($testId, $orderedIds);

            return $this->sections->ofTest($testId);
        });
    }
}
