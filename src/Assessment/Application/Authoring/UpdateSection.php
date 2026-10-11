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

/** The author changes a section. Its format is fixed when it is created, because the questions it holds depend on it. */
final readonly class UpdateSection
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private SectionFactory $factory,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-section`
     * @throws StateConflict `test-archived` or `test-locked`
     */
    public function execute(Actor $actor, int $sectionId, SectionDraft $draft): Section
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $sectionId, $draft): Section {
            $current = $this->sections->find($sectionId) ?? throw EntityNotFound::of('Section', $sectionId);
            $test = $this->access->editableTest($author, $current->testId);

            if ($draft->format !== $current->format) {
                throw new BusinessRuleViolation('The format of a section cannot be changed. Add a new section instead.', 'invalid-section', extensions: ['errors' => ['format' => ['The format of a section cannot be changed.']]]);
            }

            $section = $this->factory->build($author, $test->kind(), $current->testId, $draft, $sectionId, $current->position);
            $this->sections->update($section);

            return $section;
        });
    }
}
