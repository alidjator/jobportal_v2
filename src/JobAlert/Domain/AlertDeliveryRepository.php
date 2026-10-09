<?php

namespace Src\JobAlert\Domain;

/** Remembers which job an immediate alert already told about, so a retried or repeated event never tells twice. */
interface AlertDeliveryRepository
{
    /** Marks the job as told for the alert; false when it already was. */
    public function claim(int $alertId, int $jobId): bool;
}
