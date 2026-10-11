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

/** The psychologist writes or replaces the report template of a battery. Its placeholders must be known and name scales the battery uses. */
final readonly class SaveReportTemplate
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
                throw new BusinessRuleViolation('Only a psychological battery has a report template.', 'test-not-psychological');
            }

            $body = ReportTemplateRules::check($body, $this->questions->scalesOfTest($testId));
            $this->templates->save($testId, $body, $this->clock->now());

            return $body;
        });
    }
}
