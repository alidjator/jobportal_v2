<?php

namespace Tests\Unit\LegacyImport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\LegacyImport\Application\LegacyVacancies;

final class LegacyVacanciesTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: int|string}> */
    public static function texts(): array
    {
        return [
            'a bare number' => ['5', 5],
            'spaces around' => ['  12 ', 12],
            'a number with words' => ['5 orang', 5],
            'words before the number' => ['Dibutuhkan 10 posisi', 10],
            'a range uses the larger number' => ['2-3', 3],
            'a range in words' => ['2 sampai 4 orang', 4],
            'a range with spaces' => ['3 - 1', 3],
            'an empty text' => ['', 'vacancies-empty'],
            'null' => [null, 'vacancies-empty'],
            'no number' => ['banyak', 'vacancies-unreadable'],
            'two unrelated numbers' => ['5 orang dan 3 admin', 'vacancies-ambiguous'],
            'zero' => ['0', 'vacancies-out-of-range'],
            'too many' => ['70000', 'vacancies-out-of-range'],
            'the largest' => ['65535', 65535],
            'an overflowing number' => ['99999999999999999999999', 'vacancies-out-of-range'],
        ];
    }

    #[DataProvider('texts')]
    public function test_the_text_is_read_as_a_number_or_a_reason(?string $text, int|string $expected): void
    {
        $this->assertSame($expected, LegacyVacancies::parse($text));
    }
}
