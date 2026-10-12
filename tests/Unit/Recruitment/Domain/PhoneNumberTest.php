<?php

namespace Tests\Unit\Recruitment\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\PhoneNumber;

final class PhoneNumberTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function indonesianNumbers(): array
    {
        return [
            'local with 0' => ['081234567890', '+6281234567890'],
            'spaces and dashes' => ['0812-3456 7890', '+6281234567890'],
            'with 62' => ['6281234567890', '+6281234567890'],
            'with plus' => ['+62 812 3456 7890', '+6281234567890'],
            'with 00' => ['0062 812 3456 7890', '+6281234567890'],
            'brackets and dots' => ['(021) 5550.1234', '+622155501234'],
            'shortest' => ['0812345678', '+62812345678'],
        ];
    }

    #[DataProvider('indonesianNumbers')]
    public function test_an_indonesian_number_becomes_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalise($input));
    }

    /** @return array<string, array{string}> */
    public static function refusedNumbers(): array
    {
        return [
            'empty' => [''],
            'letters' => ['0812abc7890'],
            'no prefix' => ['81234567890'],
            'another country' => ['+14155550123'],
            'another country with 00' => ['0014155550123'],
            'too short' => ['0812345'],
            'too long' => ['0812345678901234'],
            'zero after country code' => ['+620812345678'],
            'only a plus' => ['+'],
        ];
    }

    #[DataProvider('refusedNumbers')]
    public function test_anything_else_is_refused(string $input): void
    {
        $this->assertNull(PhoneNumber::normalise($input));
    }
}
