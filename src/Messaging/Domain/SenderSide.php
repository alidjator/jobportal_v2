<?php

namespace Src\Messaging\Domain;

/** Which side of a conversation wrote a message: the company (any of its members) or the job seeker. */
enum SenderSide: string
{
    case Company = 'company';
    case Seeker = 'seeker';

    public function other(): self
    {
        return $this === self::Company ? self::Seeker : self::Company;
    }
}
