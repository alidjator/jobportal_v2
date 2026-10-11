<?php

namespace Src\Assessment\Application\Admin;

use DateTimeImmutable;
use Src\Assessment\Domain\Events\PsychologistChanged;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Records the renewed STR and SILP numbers and dates of a psychologist. */
final readonly class UpdatePsychologistCredentials
{
    public function __construct(
        private PsychologistRepository $psychologists,
        private PsychologistAccounts $accounts,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     */
    public function execute(
        Actor $actor,
        int $psychologistId,
        string $strNumber,
        DateTimeImmutable $strValidUntil,
        string $silpNumber,
        DateTimeImmutable $silpValidUntil,
        ?string $ipAddress,
        ?string $userAgent,
    ): PsychologistListItem {
        AdminOnly::assert($actor, 'change a psychologist');

        $psychologist = $this->psychologists->findById($psychologistId) ?? throw EntityNotFound::of('Psychologist', $psychologistId);

        $psychologist->updateCredentials(trim($strNumber), $strValidUntil, trim($silpNumber), $silpValidUntil);
        $psychologist = $this->psychologists->save($psychologist);

        $now = $this->clock->now();
        $this->events->dispatch([new PsychologistChanged($psychologistId, 'credentials_changed', (int) $actor->id, $ipAddress, $userAgent, $now)]);

        return new PsychologistListItem($psychologist, $this->accounts->summaries([$psychologist->userId])[$psychologist->userId] ?? null, $psychologist->canSignReports($now));
    }
}
