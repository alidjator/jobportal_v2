<?php

namespace Src\JobInvitation\Application;

/** What happened to one talent pool entry of a bulk invitation: invited (with the invitation) or skipped (with the reason code). */
final readonly class InvitationOutcome
{
    public function __construct(public int $entryId, public ?int $invitationId, public ?string $skipCode) {}

    public static function invited(int $entryId, int $invitationId): self
    {
        return new self($entryId, $invitationId, null);
    }

    public static function skipped(int $entryId, string $code): self
    {
        return new self($entryId, null, $code);
    }

    public function wasInvited(): bool
    {
        return $this->skipCode === null;
    }
}
