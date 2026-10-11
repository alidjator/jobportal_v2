<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The author adds a section after the last one of a test. */
final readonly class AddSection
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private SectionFactory $factory,
        private Settings $settings,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-section`
     * @throws StateConflict `test-archived`, `test-locked` or `section-limit-reached`
     */
    public function execute(Actor $actor, int $testId, SectionDraft $draft): Section
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $testId, $draft): Section {
            $test = $this->access->editableTest($author, $testId);

            if (count($this->sections->ofTest($testId)) >= $this->settings->integer(AssessmentSettings::MAX_SECTIONS_PER_TEST, $author->companyId())) {
                throw new StateConflict('The test has reached its limit of sections.', 'section-limit-reached');
            }

            return $this->sections->add($this->factory->build($author, $test->kind(), $testId, $draft));
        });
    }
}
