<?php

namespace Src\Assessment\Domain;

/** The hiring advice on a psychological report (`docs/design/phase-8-provider-tools.md` section 4). It is advice for the company, never shown to the candidate. */
enum Recommendation: string
{
    case Recommended = 'recommended';
    case Consider = 'consider';
    case NotYet = 'not_yet';

    /** The label the report prints, in Indonesian like the rest of the report. */
    public function label(): string
    {
        return match ($this) {
            self::Recommended => 'Disarankan',
            self::Consider => 'Dipertimbangkan',
            self::NotYet => 'Belum disarankan',
        };
    }
}
