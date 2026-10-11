<?php

namespace Src\Assessment\Application\Delivery;

use DateInterval;
use Src\Assessment\Domain\Events\InvitationChange;
use Src\Assessment\Domain\Events\InvitationChanged;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;

/**
 * Reminds the candidate of every test they have not started that is due within the next 24 hours and was not reminded yet. It runs every hour, so the reminder
 * arrives about a day before the due time; a test sent less than a day before its due time is reminded on the next run. Each invitation is reminded once.
 */
final readonly class SendInvitationReminders
{
    public const int HOURS_AHEAD = 24;

    public function __construct(
        private InvitationRepository $invitations,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return int the number of invitations reminded */
    public function execute(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->invitations->dueForReminder($now, $now->add(new DateInterval('PT'.self::HOURS_AHEAD.'H'))) as $due) {
            $event = $this->transactions->run(function () use ($due, $now): ?InvitationChanged {
                $invitation = $this->invitations->find((int) $due->id, forUpdate: true);

                // Changed since it was listed: started, cancelled, expired or already reminded.
                if (! $invitation instanceof Invitation || $invitation->status !== InvitationStatus::Invited || $invitation->remindedAt !== null || $invitation->dueAt <= $now) {
                    return null;
                }

                $reminded = $invitation->reminded($now);
                $this->invitations->update($reminded);

                return InvitationChanged::of(InvitationChange::Reminder, $reminded, $now);
            });

            if ($event instanceof InvitationChanged) {
                $this->events->dispatch([$event]);
                $count++;
            }
        }

        return $count;
    }
}
