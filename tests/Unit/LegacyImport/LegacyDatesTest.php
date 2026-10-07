<?php

namespace Tests\Unit\LegacyImport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\LegacyImport\Application\LegacyDates;

final class LegacyDatesTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function months(): array
    {
        return [
            'year and month' => ['2020-03', '2020-03-01'],
            'a full date' => ['2020-03-15', '2020-03-01'],
            'a timestamp' => ['2020-03-15 08:00:00', '2020-03-01'],
            'month and year' => ['03/2020', '2020-03-01'],
            'day month year' => ['15/03/2020', '2020-03-01'],
            'english month' => ['March 2020', '2020-03-01'],
            'short english month' => ['Mar 2020', '2020-03-01'],
            'indonesian month' => ['Agustus 2019', '2019-08-01'],
            'upper case and spaces' => ['  DESEMBER   2021 ', '2021-12-01'],
        ];
    }

    #[DataProvider('months')]
    public function test_a_month_is_read_from_the_common_spellings(string $text, string $expected): void
    {
        $this->assertSame($expected, LegacyDates::month($text)?->format('Y-m-d'));
    }

    public function test_an_unreadable_month_is_null(): void
    {
        foreach ([null, '', 'sometime', '2020-13', '13/2020', '1850-01', 'Smarch 2020', '2020'] as $text) {
            $this->assertNull(LegacyDates::month($text), (string) $text);
        }
    }

    public function test_a_year_is_found_in_the_text(): void
    {
        $this->assertSame(2019, LegacyDates::year('2019'));
        $this->assertSame(2019, LegacyDates::year('2019-06-01'));
        $this->assertSame(2018, LegacyDates::year('class of 2018'));
        $this->assertNull(LegacyDates::year('last year'));
        $this->assertNull(LegacyDates::year('20191'));
        $this->assertNull(LegacyDates::year(null));
    }

    public function test_a_day_is_read_from_a_date_or_a_timestamp(): void
    {
        $this->assertSame('2020-03-15', LegacyDates::day('2020-03-15')?->format('Y-m-d'));
        $this->assertSame('2020-03-15', LegacyDates::day('2020-03-15 10:00:00')?->format('Y-m-d'));
        $this->assertNull(LegacyDates::day('2020-02-30'));
        $this->assertNull(LegacyDates::day('15/03/2020'));
        $this->assertNull(LegacyDates::day(null));
    }
}
