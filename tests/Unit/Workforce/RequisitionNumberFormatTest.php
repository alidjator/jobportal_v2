<?php

namespace Tests\Unit\Workforce;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Workforce\Domain\RequisitionNumberFormat;

final class RequisitionNumberFormatTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function formats(): array
    {
        return [
            'the default' => [RequisitionNumberFormat::DEFAULT, true],
            'a year and a plain running number' => ['REQ/{YYYY}/{SEQ}', true],
            'two digit year and month, padded to five' => ['JR.{YY}{MM}-{SEQ:5}', true],
            'only a running number' => ['{SEQ:4}', true],
            'no running number' => ['JR-{YYYY}{MM}{DD}', false],
            'two running numbers' => ['JR-{SEQ}-{SEQ:3}', false],
            'a padding of zero' => ['JR-{SEQ:0}', false],
            'a padding of ten' => ['JR-{SEQ:10}', false],
            'an unknown token' => ['JR-{HOUR}-{SEQ}', false],
            'a space' => ['JR {SEQ}', false],
            'a stray brace' => ['JR-{SEQ}}', false],
            'empty' => ['', false],
            'too long' => [str_repeat('A', 40).'{SEQ}', false],
        ];
    }

    #[DataProvider('formats')]
    public function test_a_format_has_exactly_one_running_number_and_known_tokens(string $format, bool $valid): void
    {
        $this->assertSame($valid, RequisitionNumberFormat::isValid($format));
    }

    public function test_the_default_gives_the_date_and_a_running_number_of_three_digits(): void
    {
        $moment = new DateTimeImmutable('2026-11-01 03:00:00', new DateTimeZone('UTC'));

        $this->assertSame('JR-20261101-001', RequisitionNumberFormat::render(RequisitionNumberFormat::DEFAULT, $moment, 1));
        $this->assertSame('JR-20261101-042', RequisitionNumberFormat::render(RequisitionNumberFormat::DEFAULT, $moment, 42));
        $this->assertSame('JR-20261101-1234', RequisitionNumberFormat::render(RequisitionNumberFormat::DEFAULT, $moment, 1234));
        $this->assertSame('JR-20261101-', RequisitionNumberFormat::series(RequisitionNumberFormat::DEFAULT, $moment));
    }

    public function test_the_day_is_the_jakarta_day(): void
    {
        // 20:00 UTC on 31 October is already 1 November in Jakarta.
        $moment = new DateTimeImmutable('2026-10-31 20:00:00', new DateTimeZone('UTC'));

        $this->assertSame('JR-20261101-001', RequisitionNumberFormat::render(RequisitionNumberFormat::DEFAULT, $moment, 1));
    }

    public function test_the_series_changes_with_the_date_part_that_the_format_holds(): void
    {
        $first = new DateTimeImmutable('2026-11-01 03:00:00', new DateTimeZone('UTC'));
        $sameMonth = new DateTimeImmutable('2026-11-20 03:00:00', new DateTimeZone('UTC'));
        $nextYear = new DateTimeImmutable('2027-11-01 03:00:00', new DateTimeZone('UTC'));

        $this->assertNotSame(RequisitionNumberFormat::series('JR-{YYYY}{MM}{DD}-{SEQ}', $first), RequisitionNumberFormat::series('JR-{YYYY}{MM}{DD}-{SEQ}', $sameMonth));
        $this->assertSame(RequisitionNumberFormat::series('JR-{YYYY}{MM}-{SEQ}', $first), RequisitionNumberFormat::series('JR-{YYYY}{MM}-{SEQ}', $sameMonth));
        $this->assertNotSame(RequisitionNumberFormat::series('JR-{YYYY}-{SEQ}', $first), RequisitionNumberFormat::series('JR-{YYYY}-{SEQ}', $nextYear));
        $this->assertSame(RequisitionNumberFormat::series('JR-{SEQ}', $first), RequisitionNumberFormat::series('JR-{SEQ}', $nextYear));
        $this->assertSame('REQ/26/11/0007', RequisitionNumberFormat::render('REQ/{YY}/{MM}/{SEQ:4}', $first, 7));
    }
}
