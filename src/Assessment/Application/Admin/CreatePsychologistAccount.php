<?php

namespace Src\Assessment\Application\Admin;

use DateTimeImmutable;
use Src\Assessment\Domain\Events\PsychologistJoined;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\PsychologistRole;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The operator's way to give a psychologist an account without the invitation email (the console command `assessment:create-psychologist`): the account, with a
 * password the operator sets, and the psychologist record under an active provider. The psychologist enrols the second factor at the first sign-in, as after an invitation.
 */
final readonly class CreatePsychologistAccount
{
    public function __construct(
        private ProviderRepository $providers,
        private PsychologistRepository $psychologists,
        private PsychologistAccounts $accounts,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return array{psychologistId: int, userId: int}
     *
     * @throws EntityNotFound when the provider does not exist
     * @throws AccessDenied `provider-suspended`
     * @throws StateConflict `email-already-registered`
     */
    public function execute(
        int $providerId,
        string $name,
        string $email,
        string $password,
        PsychologistRole $role,
        string $strNumber,
        DateTimeImmutable $strValidUntil,
        string $silpNumber,
        DateTimeImmutable $silpValidUntil,
    ): array {
        $email = mb_strtolower(trim($email));

        [$psychologistId, $userId] = $this->transactions->run(function () use ($providerId, $name, $email, $password, $role, $strNumber, $strValidUntil, $silpNumber, $silpValidUntil): array {
            $provider = $this->providers->findById($providerId, forUpdate: true) ?? throw EntityNotFound::of('Provider', $providerId);

            if (! $provider->isActive()) {
                throw new AccessDenied('This provider is suspended.', 'provider-suspended');
            }

            if ($this->accounts->emailIsRegistered($email)) {
                throw new StateConflict('An account with this email already exists.', 'email-already-registered');
            }

            $userId = $this->accounts->register(trim($name), $email, $password, 'id');
            $psychologist = $this->psychologists->save(Psychologist::join($userId, $providerId, trim($strNumber), $strValidUntil, trim($silpNumber), $silpValidUntil, $role));

            return [(int) $psychologist->id, $userId];
        });

        $this->events->dispatch([new PsychologistJoined($providerId, $psychologistId, $userId, null, null, $this->clock->now())]);

        return ['psychologistId' => $psychologistId, 'userId' => $userId];
    }
}
