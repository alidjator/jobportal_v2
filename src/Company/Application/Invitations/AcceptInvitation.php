<?php

namespace Src\Company\Application\Invitations;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\Events\CompanyMemberJoined;
use Src\Company\Domain\InvitationRepository;
use Src\Shared\Application\Accounts\AccountSession;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Public: the invitee accepts, sets their name and password, and is signed in. The invitation row is locked while the account and the
 * membership are created, so a second request with the same token finds it already used.
 */
final readonly class AcceptInvitation
{
    public function __construct(
        private PendingInvitationFinder $finder,
        private InvitationRepository $invitations,
        private CompanyMemberRepository $members,
        private CompanyMemberAccounts $accounts,
        private InvitationThrottle $throttle,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws TooManyAttempts when the client address has used up its attempts
     * @throws EntityNotFound `invitation-not-found` for any token that is not a pending invitation
     * @throws AccessDenied `company-suspended`
     * @throws StateConflict `email-already-registered` when the invited email got an account since the invitation was sent
     */
    public function execute(AcceptInvitationCommand $command): AccountSession
    {
        $this->throttle->hit($command->ipAddress);

        $now = $this->clock->now();

        [$userId, $membership] = $this->transactions->run(function () use ($command, $now): array {
            [$invitation, $company] = $this->finder->find($command->token, forUpdate: true);

            if ($this->accounts->emailIsRegistered($invitation->email)) {
                throw new StateConflict('An account with this email already exists.', 'email-already-registered');
            }

            $userId = $this->accounts->register($command->name, $invitation->email, $command->password, $command->language);
            $this->members->add((int) $company->id, $userId, $invitation->role);

            $invitation->accept($now);
            $this->invitations->save($invitation);

            return [$userId, [(int) $company->id, $invitation->role->value]];
        });

        $session = $this->accounts->signIn($userId, $command->deviceName, $command->ipAddress, $command->userAgent);
        $this->events->dispatch([new CompanyMemberJoined($membership[0], $userId, $membership[1], $command->ipAddress, $command->userAgent, $now)]);

        return $session;
    }
}
