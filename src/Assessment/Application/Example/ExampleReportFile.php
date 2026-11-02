<?php

namespace Src\Assessment\Application\Example;

/** The example report of a battery as a company downloads it: the file name to offer and the bytes. */
final readonly class ExampleReportFile
{
    public function __construct(public string $name, public string $contents) {}
}
