<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The author adds a question after the last one of a section. */
final readonly class AddQuestion
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private QuestionRepository $questions,
        private QuestionFactory $factory,
        private Settings $settings,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-question` or `invalid-section`
     * @throws StateConflict `test-archived`, `test-locked` or `question-limit-reached`
     */
    public function execute(Actor $actor, int $sectionId, QuestionDraft $draft): Question
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $sectionId, $draft): Question {
            $section = $this->sections->find($sectionId) ?? throw EntityNotFound::of('Section', $sectionId);
            $test = $this->access->editableTest($author, $section->testId);

            if ($this->questions->countInSection($sectionId) >= $this->settings->integer(AssessmentSettings::MAX_QUESTIONS_PER_SECTION, $author->companyId())) {
                throw new StateConflict('The section has reached its limit of questions.', 'question-limit-reached');
            }

            return $this->questions->add($this->factory->build($test, $section, $draft));
        });
    }
}
