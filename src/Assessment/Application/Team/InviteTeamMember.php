<?php

namespace Src\Assessment\Application\Team;

use Src\Assessment\Application\Invitations\IssueInvitation;
use Src\Assessment\Domain\Events\PsychologistInvited;
use Src\Assessment\Domain\PsychologistInvitation;
use Src\Assessment\Domain\PsychologistRole;
use Src\Shared\Application\Actor;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** A lead invites a colleague to their institution. The invitation carries no licence: the colleague submits it after accepting and cannot sign until it is approved. */
final readonly class InviteTeamMember
{
    public function __construct(
        private TeamAccess $access,
        private IssueInvitation $issue,
        private EventDispatcher $events,
    ) {}

    /**
     * @throws AccessDenied `not-a-lead` and the refusals of `TeamAccess`, or `provider-suspended`
     * @throws StateConflict `email-already-registered` when the email already has an account
     * @throws TooManyAttempts `invitation-cooldown` when the previous invitation to this email was sent less than 60 seconds ago
     * @throws ExternalServiceUnavailable `invitation-link-unavailable` when the client URL template is not configured
     */
    public function execute(Actor $actor, string $email, string $name, PsychologistRole $role, string $language, ?string $ipAddress, ?string $userAgent): PsychologistInvitation
    {
        $lead = $this->access->lead($actor);

        $invitation = $this->issue->execute($lead->providerId, $email, $language, fn (string $tokenHash, $now): PsychologistInvitation => PsychologistInvitation::byLead(
            $lead->providerId, mb_strtolower(trim($email)), trim($name), $role, $tokenHash, (int) $lead->id, $now,
        ));

        $this->events->dispatch([new PsychologistInvited($lead->providerId, (int) $invitation->id, null, $lead->userId, $ipAddress, $userAgent, $invitation->sentAt)]);

        return $invitation;
    }
}
