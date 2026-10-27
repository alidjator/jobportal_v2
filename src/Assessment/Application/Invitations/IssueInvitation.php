<?php

namespace Src\Assessment\Application\Invitations;

use Closure;
use DateTimeImmutable;
use Src\Assessment\Domain\InvitationToken;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\PsychologistInvitation;
use Src\Assessment\Domain\PsychologistInvitationRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * What inviting a psychologist means whoever invites (a platform admin or a lead of the institution): the email has no account yet, a provider has at
 * most one pending invitation per email (a new one revokes the old and must come at least 60 seconds after it), and the email goes out after the commit.
 */
final readonly class IssueInvitation
{
    public const int COOLDOWN_SECONDS = 60;

    public function __construct(
        private ProviderRepository $providers,
        private PsychologistInvitationRepository $invitations,
        private PsychologistAccounts $accounts,
        private InvitationLinkBuilder $links,
        private InvitationSender $sender,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  Closure(string, DateTimeImmutable): PsychologistInvitation  $make  builds the invitation from the token hash and the time
     *
     * @throws AccessDenied `provider-suspended`
     * @throws EntityNotFound when the provider does not exist
     * @throws StateConflict `email-already-registered` when the email already has an account
     * @throws TooManyAttempts `invitation-cooldown` when the previous invitation to this email was sent less than 60 seconds ago
     * @throws ExternalServiceUnavailable `invitation-link-unavailable` when the client URL template is not configured
     */
    public function execute(int $providerId, string $email, string $language, Closure $make): PsychologistInvitation
    {
        $email = mb_strtolower(trim($email));

        if ($this->accounts->emailIsRegistered($email)) {
            throw new StateConflict('An account with this email already exists.', 'email-already-registered');
        }

        $this->links->assertAvailable();

        $token = InvitationToken::generate();

        [$invitation, $providerName] = $this->transactions->run(function () use ($providerId, $email, $token, $make): array {
            $now = $this->clock->now();
            $provider = $this->providers->findById($providerId, forUpdate: true) ?? throw EntityNotFound::of('Provider', $providerId);

            if (! $provider->isActive()) {
                throw new AccessDenied('This provider is suspended.', 'provider-suspended');
            }

            foreach ($this->invitations->pendingOfProvider($providerId, $now, forUpdate: true) as $pending) {
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

            return [$this->invitations->save($make($token->hash(), $now)), $provider->name()];
        });

        $this->sender->send($email, $language, $providerName, $this->links->build($token->plain), $invitation->expiresAt);

        return $invitation;
    }
}
