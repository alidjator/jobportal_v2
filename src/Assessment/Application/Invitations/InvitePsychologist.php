<?php

namespace Src\Assessment\Application\Invitations;

use Src\Assessment\Application\Admin\AdminOnly;
use Src\Assessment\Domain\Events\PsychologistInvited;
use Src\Assessment\Domain\PsychologistInvitation;
use Src\Shared\Application\Actor;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** A platform admin invites a psychologist to a provider and types their licence. The invitee sets their own password; the account is created when the invitation is accepted. */
final readonly class InvitePsychologist
{
    public function __construct(
        private IssueInvitation $issue,
        private EventDispatcher $events,
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

        $invitation = $this->issue->execute($command->providerId, $command->email, $command->language, fn (string $tokenHash, $now): PsychologistInvitation => PsychologistInvitation::byAdmin(
            $command->providerId, mb_strtolower(trim($command->email)), trim($command->name), trim($command->strNumber), $command->strValidUntil,
            trim($command->silpNumber), $command->silpValidUntil, $command->role, $tokenHash, (int) $actor->id, $now,
        ));

        $this->events->dispatch([new PsychologistInvited(
            $command->providerId, (int) $invitation->id, (int) $actor->id, null, $command->ipAddress, $command->userAgent, $invitation->sentAt,
        )]);

        return $invitation;
    }
}
