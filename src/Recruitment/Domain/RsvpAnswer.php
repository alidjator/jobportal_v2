<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/** What a panel member answered to the invitation: `$at` and `$note` are null while it is pending (and the note when none was given). */
final readonly class RsvpAnswer
{
    public function __construct(public Rsvp $rsvp, public ?string $note = null, public ?DateTimeImmutable $at = null) {}

    public static function pending(): self
    {
        return new self(Rsvp::Pending);
    }
}
