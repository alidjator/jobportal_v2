<?php

namespace Src\Recruitment\Domain;

/** The band of the match score an application had when it was submitted, for the analytics. `unknown` is an application without a score. */
enum MatchBand: string
{
    case Unknown = 'unknown';
    case Low = 'low';
    case Fair = 'fair';
    case Good = 'good';
    case Strong = 'strong';

    public static function of(?int $score): self
    {
        return match (true) {
            $score === null => self::Unknown,
            $score < 40 => self::Low,
            $score < 60 => self::Fair,
            $score < 80 => self::Good,
            default => self::Strong,
        };
    }
}
