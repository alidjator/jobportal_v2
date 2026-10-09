<?php

namespace Src\Shared\Application\Recruitment;

use DateTimeImmutable;

/** Lets other contexts count applications without reading Recruitment's tables. Provided by Recruitment. */
interface ApplicationCounts
{
    /**
     * How many applications each of these jobs received since the moment.
     *
     * @param  list<int>  $jobIds
     * @return array<int, int> job id => count, jobs without an application missing
     */
    public function perJob(array $jobIds, DateTimeImmutable $since): array;

    /**
     * How many applications these jobs received per Jakarta calendar day since the moment.
     *
     * @param  list<int>  $jobIds
     * @return array<string, int> `Y-m-d` => count, quiet days missing
     */
    public function perDay(array $jobIds, DateTimeImmutable $since): array;
}
