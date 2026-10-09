<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlertRepository;

/** Deletes the alerts of a user whose account was anonymised (their deliveries go with them). Running it again does nothing. */
final readonly class EraseJobAlerts
{
    public function __construct(private JobAlertRepository $alerts) {}

    public function execute(int $userId): void
    {
        $this->alerts->deleteOfUser($userId);
    }
}
