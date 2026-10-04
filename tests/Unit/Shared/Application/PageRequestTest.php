<?php

namespace Tests\Unit\Shared\Application;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Application\Pagination\PageRequest;

final class PageRequestTest extends TestCase
{
    public function test_the_defaults_are_page_one_with_twenty_items(): void
    {
        $request = new PageRequest;

        $this->assertSame(1, $request->page);
        $this->assertSame(20, $request->perPage);
        $this->assertSame(0, $request->offset());
    }

    public function test_the_offset_follows_the_page(): void
    {
        $this->assertSame(40, (new PageRequest(3, 20))->offset());
        $this->assertSame(100, (new PageRequest(2, 100))->offset());
    }

    public function test_the_limits_are_one_and_one_hundred_inclusive(): void
    {
        $this->assertSame(1, (new PageRequest(1, 1))->perPage);
        $this->assertSame(100, (new PageRequest(1, 100))->perPage);
    }

    /** @return array<string, array{int, int}> */
    public static function outOfRange(): array
    {
        return ['page zero' => [0, 20], 'negative page' => [-1, 20], 'per page zero' => [1, 0], 'per page over the maximum' => [1, 101]];
    }

    #[DataProvider('outOfRange')]
    public function test_values_out_of_range_are_refused_not_capped(int $page, int $perPage): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageRequest($page, $perPage);
    }
}
