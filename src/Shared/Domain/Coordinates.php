<?php

namespace Src\Shared\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A point inside the bounding box of Indonesia (addresses are Indonesian only). It is not matched against the province. */
final readonly class Coordinates
{
    public const float LATITUDE_MIN = -11.0;

    public const float LATITUDE_MAX = 6.0;

    public const float LONGITUDE_MIN = 95.0;

    public const float LONGITUDE_MAX = 141.0;

    public function __construct(public float $latitude, public float $longitude) {}

    /** True when both values are inside the bounding box. */
    public static function fits(float $latitude, float $longitude): bool
    {
        return $latitude >= self::LATITUDE_MIN && $latitude <= self::LATITUDE_MAX && $longitude >= self::LONGITUDE_MIN && $longitude <= self::LONGITUDE_MAX;
    }

    /** @throws BusinessRuleViolation `invalid-coordinates` */
    public static function of(float $latitude, float $longitude): self
    {
        if (! self::fits($latitude, $longitude)) {
            throw new BusinessRuleViolation('The coordinates must be inside Indonesia: latitude -11 to 6, longitude 95 to 141.', 'invalid-coordinates');
        }

        return new self($latitude, $longitude);
    }
}
