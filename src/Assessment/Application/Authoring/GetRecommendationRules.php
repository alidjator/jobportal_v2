<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\RecommendationRuleRepository;
use Src\Assessment\Domain\RecommendationRules;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The psychologist reads the rule that works out the recommendation of the reports a battery of their provider issues automatically; null when it has none. */
final readonly class GetRecommendationRules
{
    public function __construct(private AuthoringAccess $access, private RecommendationRuleRepository $rules) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId): ?RecommendationRules
    {
        $this->access->ownedTest($this->access->author($actor), $testId);

        return $this->rules->ofTest($testId);
    }
}
