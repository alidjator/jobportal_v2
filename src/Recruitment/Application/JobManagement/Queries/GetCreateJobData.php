<?php

namespace Src\Recruitment\Application\JobManagement\Queries;

use Src\Recruitment\Application\JobManagement\Ports\JobFormOptions;

final class GetCreateJobData
{
    public function __construct(private readonly JobFormOptions $options) {}

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return $this->options->forCreate();
    }
}
