<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\ReportTemplateRepository;
use Src\Assessment\Domain\ReportTemplateRules;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The psychologist writes or replaces the explanation template of a battery: the plain-language text an automatically issued report gives the candidate. It takes the
 * placeholders of the report template plus `{{scale:KEY:explanation}}`, the explanation of the norm range the raw score falls in.
 */
final readonly class SaveExplanationTemplate
{
    public function __construct(
        private AuthoringAccess $access,
        private QuestionRepository $questions,
        private ReportTemplateRepository $templates,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-archived` or `test-locked`
     * @throws BusinessRuleViolation `test-not-psychological` or `invalid-report-template`
     */
    public function execute(Actor $actor, int $testId, string $body): string
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $testId, $body): string {
            $test = $this->access->editableTest($author, $testId);

            if ($test->kind() !== TestKind::Psychological) {
                throw new BusinessRuleViolation('Only a psychological battery has an explanation template.', 'test-not-psychological');
            }

            $body = ReportTemplateRules::check($body, $this->questions->scalesOfTest($testId), forExplanation: true);
            $this->templates->saveExplanationTemplate($testId, $body, $this->clock->now());

            return $body;
        });
    }
}
