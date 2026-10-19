<?php

namespace Tests\Unit\Company;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Company\Application\Locations\LocationTimeZones;

final class LocationTimeZonesTest extends TestCase
{
    /** @return array<string, array{?string, ?string}> */
    public static function provinces(): array
    {
        return [
            'Aceh' => ['11', 'Asia/Jakarta'],
            'DKI Jakarta' => ['31', 'Asia/Jakarta'],
            'Central Kalimantan' => ['62', 'Asia/Jakarta'],
            'Bali' => ['51', 'Asia/Makassar'],
            'South Kalimantan' => ['63', 'Asia/Makassar'],
            'South Sulawesi' => ['73', 'Asia/Makassar'],
            'Maluku' => ['81', 'Asia/Jayapura'],
            'Papua' => ['91', 'Asia/Jayapura'],
            'South Papua' => ['93', 'Asia/Jayapura'],
            'a longer code keeps its province' => ['5171', 'Asia/Makassar'],
            'no province' => [null, null],
            'an empty code' => ['', null],
        ];
    }

    #[DataProvider('provinces')]
    public function test_the_zone_follows_the_province_code(?string $code, ?string $zone): void
    {
        $this->assertSame($zone, LocationTimeZones::forProvinceCode($code));
    }
}
