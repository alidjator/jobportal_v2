<?php

namespace Src\Recruitment\Domain;

/** Where an applicant came from when they applied. The server decides it: the client only hints, and an invitation always wins. */
enum ApplicationSource: string
{
    case Search = 'search';
    case Recommendation = 'recommendation';
    case Alert = 'alert';
    case Direct = 'direct';
    case Invitation = 'invitation';
    case Shared = 'shared';

    /** The hints a client may send; `invitation` is never one of them. */
    public const array HINTS = ['search', 'recommendation', 'alert', 'direct', 'shared'];

    /** The source for a hint from the client: `direct` when it is missing or not one of the allowed values. */
    public static function fromHint(?string $hint): self
    {
        return $hint !== null && in_array($hint, self::HINTS, true) ? self::from($hint) : self::Direct;
    }
}
