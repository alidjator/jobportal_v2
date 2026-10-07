<?php

namespace Tests\Unit\LegacyImport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\LegacyImport\Application\NameNormalizer;

final class NameNormalizerTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function equalPairs(): array
    {
        return [
            'letter case' => ['Full Time', 'full time'],
            'spaces' => ['  Full   Time ', 'full time'],
            'punctuation' => ['Full-Time', 'full time'],
            'ampersand' => ['Hardware & Software', 'hardware and software'],
            'accents' => ['Café', 'cafe'],
            'slashes and dots' => ['S1/D4.', 's1 d4'],
        ];
    }

    #[DataProvider('equalPairs')]
    public function test_spellings_of_one_name_become_equal(string $name, string $expected): void
    {
        $this->assertSame($expected, NameNormalizer::normalize($name));
    }

    public function test_different_names_stay_different_and_an_empty_name_stays_empty(): void
    {
        $this->assertNotSame(NameNormalizer::normalize('Part Time'), NameNormalizer::normalize('Full Time'));
        $this->assertSame('', NameNormalizer::normalize(' -- '));
    }
}
