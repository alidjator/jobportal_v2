<?php

namespace Src\Assessment\Application\Admin;

use DateTimeImmutable;
use Src\Assessment\Application\Team\TeamMemberChanges;
use Src\Assessment\Domain\Events\PsychologistChanged;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\PsychologistRole;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Records the renewed STR and SILP numbers and dates of a psychologist, and the role when the admin sends one. */
final readonly class UpdatePsychologistCredentials
{
    public function __construct(
        private PsychologistRepository $psychologists,
        private TeamMemberChanges $changes,
        private PsychologistAccounts $accounts,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     * @throws StateConflict `last-lead` when the role would take the last active lead from the institution
     */
    public function execute(
        Actor $actor,
        int $psychologistId,
        string $strNumber,
        DateTimeImmutable $strValidUntil,
        string $silpNumber,
        DateTimeImmutable $silpValidUntil,
        ?PsychologistRole $role,
        ?string $ipAddress,
        ?string $userAgent,
    ): PsychologistListItem {
        AdminOnly::assert($actor, 'change a psychologist');

        $psychologist = $this->psychologists->findById($psychologistId) ?? throw EntityNotFound::of('Psychologist', $psychologistId);
        $roleChanged = false;

        if ($role !== null) {
            [$psychologist, $roleChanged] = $this->changes->changeRole($psychologistId, $role, null);
        }

        $psychologist->updateCredentials(trim($strNumber), $strValidUntil, trim($silpNumber), $silpValidUntil);
        $psychologist = $this->psychologists->save($psychologist);

        $now = $this->clock->now();
        $events = [new PsychologistChanged($psychologistId, 'credentials_changed', (int) $actor->id, null, $ipAddress, $userAgent, $now)];

        if ($roleChanged) {
            $events[] = new PsychologistChanged($psychologistId, 'role_changed', (int) $actor->id, null, $ipAddress, $userAgent, $now);
        }

        $this->events->dispatch($events);

        return new PsychologistListItem($psychologist, $this->accounts->summaries([$psychologist->userId])[$psychologist->userId] ?? null, $psychologist->canSignReports($now));
    }
}
