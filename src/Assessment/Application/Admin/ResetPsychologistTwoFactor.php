<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\Events\PsychologistChanged;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Removes the second factor of a psychologist who lost the device. The psychologist enrols a new one at the next sign-in. */
final readonly class ResetPsychologistTwoFactor
{
    public function __construct(
        private PsychologistRepository $psychologists,
        private PsychologistAccounts $accounts,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $psychologistId, ?string $ipAddress, ?string $userAgent): void
    {
        AdminOnly::assert($actor, 'reset the second factor of a psychologist');

        $psychologist = $this->psychologists->findById($psychologistId) ?? throw EntityNotFound::of('Psychologist', $psychologistId);

        $this->transactions->run(fn () => $this->accounts->resetTwoFactor($psychologist->userId));

        $this->events->dispatch([new PsychologistChanged($psychologistId, 'two_factor_reset', (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
