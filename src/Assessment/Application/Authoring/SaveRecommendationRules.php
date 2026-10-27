<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\RecommendationRuleRepository;
use Src\Assessment\Domain\RecommendationRules;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The psychologist sets, or with null removes, the rule that works out the recommendation of the reports a battery issues automatically: the key scales (scales the questions of the
 * battery use) and two thresholds on the standard score. Like the norms it is content of the battery, so it is locked once candidates have been invited.
 */
final readonly class SaveRecommendationRules
{
    public function __construct(
        private AuthoringAccess $access,
        private QuestionRepository $questions,
        private RecommendationRuleRepository $rules,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<string>|null  $keyScales  null removes the rule
     *
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-archived` or `test-locked`
     * @throws BusinessRuleViolation `test-not-psychological` or `invalid-recommendation-rules`
     */
    public function execute(Actor $actor, int $testId, ?array $keyScales, float $recommendedMin = 0.0, float $notYetBelow = 0.0): ?RecommendationRules
    {
        $author = $this->access->author($actor);

        return $this->transactions->run(function () use ($author, $testId, $keyScales, $recommendedMin, $notYetBelow): ?RecommendationRules {
            $test = $this->access->editableTest($author, $testId);

            if ($test->kind() !== TestKind::Psychological) {
                throw new BusinessRuleViolation('Only a psychological battery has a recommendation rule.', 'test-not-psychological');
            }

            $rules = $keyScales === null ? null : RecommendationRules::checked($keyScales, $recommendedMin, $notYetBelow, $this->questions->scalesOfTest($testId));
            $this->rules->replace($testId, $rules);

            return $rules;
        });
    }
}
