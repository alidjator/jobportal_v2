<?php

namespace Src\Recruitment\Domain;

/** What a pre-employment check is about. */
enum CheckKind: string
{
    case Medical = 'medical';
    case Background = 'background';
    case Reference = 'reference';
    case PoliceRecord = 'police_record';
    case Education = 'education';
    case Other = 'other';

    /** The kinds that always need the candidate's consent, whatever the company chose. */
    public function forcesConsent(): bool
    {
        return $this === self::Medical || $this === self::Background || $this === self::PoliceRecord;
    }

    /** The kinds whose outcome is sensitive: every read of it is audited. */
    public function auditsReads(): bool
    {
        return $this === self::Medical || $this === self::PoliceRecord;
    }

    /** A medical check keeps a category only and never a file. */
    public function acceptsFile(): bool
    {
        return $this !== self::Medical;
    }
}
