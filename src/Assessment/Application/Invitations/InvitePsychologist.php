<?php

namespace Src\Assessment\Application\Invitations;

use Src\Assessment\Application\Admin\AdminOnly;
use Src\Assessment\Domain\Events\PsychologistInvited;
use Src\Assessment\Domain\InvitationToken;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistInvitation;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * A platform admin invites a psychologist to a provider. A provider has at most one pending invitation per email: a new one revokes the old,
 * and must come at least 60 seconds after it. The invitee sets their own password; the account is created when the invitation is accepted.
 */
final readonly class InvitePsychologist
{
    public const int COOLDOWN_SECONDS = 60;

    public function __construct(
        private ProviderRepository $providers,
        private PsychologistInvitationRepository $invitations,
        private PsychologistAccounts $accounts,
        private InvitationLinkBuilder $links,
        private InvitationSender $sender,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin, or `provider-suspended`
     * @throws EntityNotFound when the provider does not exist
     * @throws StateConflict `email-already-registered` when the email already has an account
     * @throws TooManyAttempts `invitation-cooldown` when the previous invitation to this email was sent less than 60 seconds ago
     * @throws ExternalServiceUnavailable `invitation-link-unavailable` when the client URL template is not configured
     */
    public function execute(Actor $actor, InvitePsychologistCommand $command): PsychologistInvitation
    {
        AdminOnly::assert($actor, 'invite a psychologist');

        $email = mb_strtolower(trim($command->email));

        if ($this->accounts->emailIsRegistered($email)) {
            throw new StateConflict('An account with this email already exists.', 'email-already-registered');
        }

        $this->links->assertAvailable();

        $token = InvitationToken::generate();

        [$invitation, $providerName] = $this->transactions->run(function () use ($actor, $command, $email, $token): array {
            $now = $this->clock->now();
            $provider = $this->providers->findById($command->providerId, forUpdate: true) ?? throw EntityNotFound::of('Provider', $command->providerId);

            if (! $provider->isActive()) {
                throw new AccessDenied('This provider is suspended.', 'provider-suspended');
            }

            foreach ($this->invitations->pendingOfProvider($command->providerId, $now, forUpdate: true) as $pending) {
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

            $invitation = $this->invitations->save(PsychologistInvitation::issue(
                $command->providerId, $email, trim($command->name), trim($command->strNumber), $command->strValidUntil,
                trim($command->silpNumber), $command->silpValidUntil, $token->hash(), (int) $actor->id, $now,
            ));

            return [$invitation, $provider->name()];
        });

        $this->sender->send($email, $command->language, $providerName, $this->links->build($token->plain), $invitation->expiresAt);
        $this->events->dispatch([new PsychologistInvited(
            $command->providerId, (int) $invitation->id, (int) $actor->id, $command->ipAddress, $command->userAgent, $invitation->sentAt,
        )]);

        return $invitation;
    }
}
