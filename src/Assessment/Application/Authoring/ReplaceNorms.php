<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Norm;
use Src\Assessment\Domain\NormRepository;
use Src\Assessment\Domain\NormRules;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The psychologist replaces the whole norm table of a battery. Every row names a scale the battery's questions use, and the ranges of a scale do not overlap. */
final readonly class ReplaceNorms
{
    public function __construct(
        private AuthoringAccess $access,
        private QuestionRepository $questions,
        private NormRepository $norms,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<Norm>  $norms
     * @return list<Norm>
     *
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-archived` or `test-locked`
     * @throws BusinessRuleViolation `test-not-psychological` or `invalid-norms`
     */
    public function execute(Actor $actor, int $testId, array $norms): array
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $testId, $norms): array {
            $test = $this->access->editableTest($author, $testId);

            if ($test->kind() !== TestKind::Psychological) {
                throw new BusinessRuleViolation('Only a psychological battery has norms.', 'test-not-psychological');
            }

            $clean = NormRules::check($norms, $this->questions->scalesOfTest($testId));
            $this->norms->replace($testId, $clean);

            return $clean;
        });
    }
}
