<?php

namespace Src\Job\Application\Browse;

interface JobProfileReader
{
    public function find(int $jobId): ?JobProfileRecord;
}
