<?php

namespace Tests\Unit\Shared\Application;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Application\Pagination\CursorRequest;

final class CursorRequestTest extends TestCase
{
    public function test_the_defaults_are_the_first_slice_of_twenty(): void
    {
        $request = new CursorRequest;

        $this->assertNull($request->cursor);
        $this->assertSame(20, $request->perPage);
    }

    public function test_the_page_size_limits_are_one_and_one_hundred_inclusive(): void
    {
        $this->assertSame(1, (new CursorRequest('abc', 1))->perPage);
        $this->assertSame(100, (new CursorRequest(null, 100))->perPage);
        $this->assertSame('abc', (new CursorRequest('abc', 1))->cursor);
    }

    /** @return array<string, array{int}> */
    public static function outOfRange(): array
    {
        return ['zero' => [0], 'negative' => [-5], 'over the maximum' => [101]];
    }

    #[DataProvider('outOfRange')]
    public function test_a_page_size_out_of_range_is_refused_not_capped(int $perPage): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CursorRequest(null, $perPage);
    }
}
