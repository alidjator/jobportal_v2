<?php

namespace Src\Assessment\Domain;

interface ExampleReportRepository
{
    public function find(int $testId): ?ExampleReport;

    /** Keeps the example of the battery, replacing the one it had. */
    public function save(int $testId, ExampleReport $example): void;

    public function delete(int $testId): void;
}
