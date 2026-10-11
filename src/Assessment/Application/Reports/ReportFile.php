<?php

namespace Src\Assessment\Application\Reports;

/** The PDF of a signed report: its bytes and the file name to offer. */
final readonly class ReportFile
{
    public function __construct(public string $name, public string $contents) {}
}
