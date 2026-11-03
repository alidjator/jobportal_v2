<?php

namespace Src\Assessment\Domain;

/** An active psychologist of an active provider with how many reviews wait at their provider now (reports to claim and automatic reports nobody reviewed) and how many waited when they were last told. */
final readonly class ReviewsWaiting
{
    public function __construct(public int $psychologistId, public int $userId, public int $providerId, public int $count, public int $notified) {}
}
