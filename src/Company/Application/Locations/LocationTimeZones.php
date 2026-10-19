<?php

namespace Src\Company\Application\Locations;

/** The time zone of a work location, worked out from the BPS code of its province (the first two digits); null for a location without a province. */
final class LocationTimeZones
{
    private const string WIB = 'Asia/Jakarta';

    private const string WITA = 'Asia/Makassar';

    private const string WIT = 'Asia/Jayapura';

    /** Provinces that are not on Western Indonesia Time, keyed by their BPS code. Every other province (Sumatra, Java, West and Central Kalimantan) is WIB. */
    private const array ZONES = [
        '51' => self::WITA, '52' => self::WITA, '53' => self::WITA, '63' => self::WITA, '64' => self::WITA, '65' => self::WITA,
        '71' => self::WITA, '72' => self::WITA, '73' => self::WITA, '74' => self::WITA, '75' => self::WITA, '76' => self::WITA,
        '81' => self::WIT, '82' => self::WIT, '91' => self::WIT, '92' => self::WIT, '93' => self::WIT, '94' => self::WIT, '95' => self::WIT, '96' => self::WIT,
    ];

    public static function forProvinceCode(?string $bpsCode): ?string
    {
        if ($bpsCode === null || $bpsCode === '') {
            return null;
        }

        return self::ZONES[substr($bpsCode, 0, 2)] ?? self::WIB;
    }
}
