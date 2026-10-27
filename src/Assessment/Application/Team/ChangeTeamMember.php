<?php

namespace Src\Assessment\Application\Team;

use Src\Assessment\Application\Admin\PsychologistListItem;
use Src\Assessment\Domain\Events\PsychologistChanged;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\PsychologistRole;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A lead suspends, restores or changes the role of a psychologist of their institution; a psychologist of another institution is a 404. */
final readonly class ChangeTeamMember
{
    public function __construct(
        private TeamAccess $access,
        private TeamMemberChanges $changes,
        private PsychologistAccounts $accounts,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess`
     * @throws EntityNotFound
     * @throws StateConflict `cannot-suspend-self` or `last-lead`
     */
    public function suspend(Actor $actor, int $psychologistId, ?string $ipAddress, ?string $userAgent): void
    {
        $lead = $this->access->lead($actor);

        $this->changes->suspend($psychologistId, $lead->providerId, $lead->userId);
        $this->record($lead, $psychologistId, 'suspended', $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess`
     * @throws EntityNotFound
     */
    public function restore(Actor $actor, int $psychologistId, ?string $ipAddress, ?string $userAgent): void
    {
        $lead = $this->access->lead($actor);

        $this->changes->restore($psychologistId, $lead->providerId);
        $this->record($lead, $psychologistId, 'restored', $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess`
     * @throws EntityNotFound
     * @throws StateConflict `last-lead`
     */
    public function changeRole(Actor $actor, int $psychologistId, PsychologistRole $role, ?string $ipAddress, ?string $userAgent): PsychologistListItem
    {
        $lead = $this->access->lead($actor);

        [$psychologist, $changed] = $this->changes->changeRole($psychologistId, $role, $lead->providerId);

        if ($changed) {
            $this->record($lead, $psychologistId, 'role_changed', $ipAddress, $userAgent);
        }

        return $this->member($psychologist);
    }

    private function member(Psychologist $psychologist): PsychologistListItem
    {
        return new PsychologistListItem($psychologist, $this->accounts->summaries([$psychologist->userId])[$psychologist->userId] ?? null, $psychologist->canSignReports($this->clock->now()));
    }

    private function record(Psychologist $lead, int $psychologistId, string $change, ?string $ipAddress, ?string $userAgent): void
    {
        $this->events->dispatch([new PsychologistChanged($psychologistId, $change, null, $lead->userId, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
