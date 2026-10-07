<?php

namespace Src\LegacyImport\Application;

/** Keeps the report of a run where only the operator can read it. */
interface ReportWriter
{
    /**
     * @param  array<string, mixed>  $report
     * @return string where the report was written
     */
    public function write(array $report, string $runName): string;
}
