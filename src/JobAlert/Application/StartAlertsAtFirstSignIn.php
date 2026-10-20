<?php

namespace Src\JobAlert\Application;

use DateTimeImmutable;
use Src\JobAlert\Domain\JobAlertRepository;

/**
 * An account's first sign-in moves the cursor of its alerts to that moment. Alerts that exist before the first sign-in were imported from karir.pkp and held
 * back (`docs/design/phase-8-activation.md` section 4), so the first digest holds only the jobs published after the owner came back, never the backlog.
 */
final readonly class StartAlertsAtFirstSignIn
{
    public function __construct(private JobAlertRepository $alerts) {}

    public function execute(int $userId, DateTimeImmutable $at): void
    {
        $this->alerts->moveCursorOfUser($userId, $at);
    }
}
