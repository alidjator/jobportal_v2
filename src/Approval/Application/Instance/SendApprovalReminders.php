<?php

namespace Src\Approval\Application\Instance;

use Src\Approval\Application\ApprovalSettings;
use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;

/**
 * Reminds the approvers of every step that has waited `approvals.reminder_days` days (company overridable, default 2) since it started or since the last reminder, and again
 * every that many days until it is decided. A step with nobody who can decide it (every place vacant) is left until an admin names a replacement.
 */
final readonly class SendApprovalReminders
{
    public function __construct(
        private ApprovalInstanceRepository $instances,
        private ApprovalNotifier $notifier,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /** @return int how many approvals were reminded about */
    public function execute(): int
    {
        $now = $this->clock->now();
        $sent = 0;

        foreach ($this->instances->pendingIds() as $id) {
            $instance = $this->instances->find($id);
            $step = $instance?->currentStep();

            if ($instance === null || $step === null || ! $step->reminderDue($now, $this->settings->integer(ApprovalSettings::REMINDER_DAYS, $instance->companyId))) {
                continue;
            }

            if ($this->notifier->approvers($instance, true) === 0) {
                continue;
            }

            $instance->markReminded($now);
            $this->instances->save($instance);
            $sent++;
        }

        return $sent;
    }
}
