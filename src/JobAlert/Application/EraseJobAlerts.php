<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Accounts\ErasureStep;

/** Deletes the alerts of a user whose account was anonymised (their deliveries go with them). Running it again does nothing. */
final readonly class EraseJobAlerts implements ErasureStep
{
    public function __construct(private JobAlertRepository $alerts) {}

    public function name(): string
    {
        return 'job-alerts';
    }

    public function execute(int $userId): void
    {
        $this->alerts->deleteOfUser($userId);
    }
}
