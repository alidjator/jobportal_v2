<?php

namespace Src\Assessment\Domain;

/** The band of a skill test result. The thresholds are admin settings. */
enum ResultBand: string
{
    case Low = 'low';
    case Fair = 'fair';
    case Good = 'good';
    case Excellent = 'excellent';

    public static function forPercentage(float $percentage, int $fairFrom, int $goodFrom, int $excellentFrom): self
    {
        return match (true) {
            $percentage >= $excellentFrom => self::Excellent,
            $percentage >= $goodFrom => self::Good,
            $percentage >= $fairFrom => self::Fair,
            default => self::Low,
        };
    }
}
