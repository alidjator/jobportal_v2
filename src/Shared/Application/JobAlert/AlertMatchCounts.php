<?php

namespace Src\Shared\Application\JobAlert;

/** Lets JobSeeker tell a job seeker how many live jobs their alerts match, without reading JobAlert's tables. Provided by JobAlert. */
interface AlertMatchCounts
{
    /** How many different live jobs match at least one alert of the user that is not paused; counted up to a cap per alert, so a large number is a lower bound. */
    public function liveJobCount(int $userId): int;
}
