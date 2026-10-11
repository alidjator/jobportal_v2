<?php

namespace Src\Assessment\Domain;

interface NormRepository
{
    /** @return list<Norm> by scale and range */
    public function ofTest(int $testId): array;

    /**
     * Replaces the norm table of the test.
     *
     * @param  list<Norm>  $norms
     */
    public function replace(int $testId, array $norms): void;
}
