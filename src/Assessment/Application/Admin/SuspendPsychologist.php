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

/** Suspends a psychologist: the account is deactivated and its tokens are revoked, so the psychologist is signed out everywhere. */
final readonly class SuspendPsychologist
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
        AdminOnly::assert($actor, 'suspend a psychologist');

        $this->transactions->run(function () use ($psychologistId): void {
            $psychologist = $this->psychologists->findById($psychologistId, forUpdate: true) ?? throw EntityNotFound::of('Psychologist', $psychologistId);

            $psychologist->suspend();
            $this->psychologists->save($psychologist);
            $this->accounts->deactivate($psychologist->userId);
        });

        $this->events->dispatch([new PsychologistChanged($psychologistId, 'suspended', (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
