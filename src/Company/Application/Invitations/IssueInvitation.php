<?php

namespace Src\Company\Application\Invitations;

use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyInvitationSent;
use Src\Company\Domain\Invitation;
use Src\Company\Domain\InvitationRepository;
use Src\Company\Domain\InvitationToken;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Sends an invitation to join a company. Shared by the company admin who invites a colleague and the platform admin who invites a
 * company admin again. A company has at most one pending invitation per email: a new one revokes the old, and must come at least
 * 60 seconds after it. Every invitation sent is announced with `CompanyInvitationSent`, which is audited.
 */
final readonly class IssueInvitation
{
    public const int COOLDOWN_SECONDS = 60;

    public function __construct(
        private CompanyRepository $companies,
        private InvitationRepository $invitations,
        private CompanyMemberAccounts $accounts,
        private InvitationLinkBuilder $links,
        private InvitationSender $sender,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws StateConflict `email-already-registered` when the email already has an account
     * @throws TooManyAttempts `invitation-cooldown` when the previous invitation to this email was sent less than 60 seconds ago
     * @throws ExternalServiceUnavailable `invitation-link-unavailable` when the client URL template is not configured
     */
    public function execute(IssueInvitationCommand $command): Invitation
    {
        $email = mb_strtolower(trim($command->email));

        if ($this->accounts->emailIsRegistered($email)) {
            throw new StateConflict('An account with this email already exists.', 'email-already-registered');
        }

        $this->links->assertAvailable();

        $token = InvitationToken::generate();
        $companyId = (int) $command->company->id;

        $invitation = $this->transactions->run(function () use ($command, $email, $token, $companyId): Invitation {
            $now = $this->clock->now();
            $this->companies->lock($companyId);

            foreach ($this->invitations->pendingOfCompany($companyId, $now, forUpdate: true) as $pending) {
                if ($pending->email !== $email) {
                    continue;
                }

                $wait = $pending->sentAt->getTimestamp() + self::COOLDOWN_SECONDS - $now->getTimestamp();

                if ($wait > 0) {
                    throw new TooManyAttempts('Wait a moment before sending another invitation to this email.', $wait, 'invitation-cooldown');
                }

                $pending->revoke($now);
                $this->invitations->save($pending);
            }

            return $this->invitations->save(Invitation::issue(
                $companyId, $email, $command->role, $token->hash(), $command->invitedByUserId, $command->invitedByAdminId, $now,
            ));
        });

        $this->sender->send($email, $command->language, $command->company->name(), $command->role, $this->links->build($token->plain), $invitation->expiresAt);
        $this->events->dispatch([new CompanyInvitationSent(
            $companyId, (int) $invitation->id, $command->role->value, $command->invitedByUserId, $command->invitedByAdminId,
            $command->ipAddress, $command->userAgent, $invitation->sentAt,
        )]);

        return $invitation;
    }
}
