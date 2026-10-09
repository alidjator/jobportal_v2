<?php

namespace Src\JobSeeker\Domain;

/** The built-in CV templates. The set is fixed in code (each has a view under `resources/views/cv`); an admin only switches them on or off and picks the default. */
enum CvTemplate: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Compact = 'compact';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Modern => 'Modern',
            self::Compact => 'Compact',
        };
    }
}
