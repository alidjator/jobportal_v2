<?php

namespace Src\Assessment\Domain;

/** How many integrity signals an attempt raised, as a tier. It is shown to the team and never rejects a candidate. */
enum IntegrityTier: string
{
    case None = 'none';
    case Some = 'some';
    case Major = 'major';

    public const int MAJOR_FROM = 5;

    public static function forCount(int $events): self
    {
        return match (true) {
            $events <= 0 => self::None,
            $events < self::MAJOR_FROM => self::Some,
            default => self::Major,
        };
    }
}
