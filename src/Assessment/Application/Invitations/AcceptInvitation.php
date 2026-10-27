<?php

namespace Src\Assessment\Application\Invitations;

use Src\Assessment\Domain\Events\PsychologistJoined;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Public: the invitee sets a password and gets the psychologist account. The invitation row is locked while the account and the psychologist
 * record are created, so a second request with the same token finds it already used.
 */
final readonly class AcceptInvitation
{
    public function __construct(
        private PendingInvitationFinder $finder,
        private PsychologistInvitationRepository $invitations,
        private PsychologistRepository $psychologists,
        private PsychologistAccounts $accounts,
        private InvitationThrottle $throttle,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws TooManyAttempts when the client address has used up its attempts
     * @throws EntityNotFound `invitation-not-found` for any token that is not a pending invitation
     * @throws AccessDenied `provider-suspended`
     * @throws StateConflict `email-already-registered` when the invited email got an account since the invitation was sent
     */
    public function execute(AcceptInvitationCommand $command): AcceptedInvitation
    {
        $this->throttle->hit($command->ipAddress);

        $now = $this->clock->now();

        [$accepted, $providerId, $psychologistId, $userId] = $this->transactions->run(function () use ($command, $now): array {
            [$invitation, $provider] = $this->finder->find($command->token, forUpdate: true);

            if ($this->accounts->emailIsRegistered($invitation->email)) {
                throw new StateConflict('An account with this email already exists.', 'email-already-registered');
            }

            $userId = $this->accounts->register($invitation->name, $invitation->email, $command->password, $command->language);
            $psychologist = $this->psychologists->save(Psychologist::join(
                $userId, (int) $provider->id, $invitation->strNumber, $invitation->strValidUntil, $invitation->silpNumber, $invitation->silpValidUntil, $invitation->role,
            ));

            $invitation->accept($now);
            $this->invitations->save($invitation);

            return [new AcceptedInvitation($invitation->email, $provider->name()), (int) $provider->id, (int) $psychologist->id, $userId];
        });

        $this->events->dispatch([new PsychologistJoined($providerId, $psychologistId, $userId, $command->ipAddress, $command->userAgent, $now)]);

        return $accepted;
    }
}
