<?php

namespace Src\Assessment\Domain;

interface RecommendationRuleRepository
{
    public function ofTest(int $testId): ?RecommendationRules;

    /** Replaces the rule of the test, or removes it when `$rules` is null. */
    public function replace(int $testId, ?RecommendationRules $rules): void;
}
