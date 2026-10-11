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

final readonly class RestorePsychologist
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
        AdminOnly::assert($actor, 'restore a psychologist');

        $this->transactions->run(function () use ($psychologistId): void {
            $psychologist = $this->psychologists->findById($psychologistId, forUpdate: true) ?? throw EntityNotFound::of('Psychologist', $psychologistId);

            $psychologist->restore();
            $this->psychologists->save($psychologist);
            $this->accounts->reactivate($psychologist->userId);
        });

        $this->events->dispatch([new PsychologistChanged($psychologistId, 'restored', (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
