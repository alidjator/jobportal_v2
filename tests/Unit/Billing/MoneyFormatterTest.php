<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;
use Src\Billing\Domain\MoneyFormatter;

class MoneyFormatterTest extends TestCase
{
    public function test_short_amounts(): void
    {
        $this->assertSame('999', MoneyFormatter::short(999));
        $this->assertSame('2K', MoneyFormatter::short(1500));
        $this->assertSame('3M', MoneyFormatter::short(2_500_000));
        $this->assertSame('20K', MoneyFormatter::short(10, 2000));
    }

    public function test_symbol_position(): void
    {
        $this->assertSame('$ 10.5', MoneyFormatter::withSymbol(10.5, '$'));
        $this->assertSame('10.5 Rp', MoneyFormatter::withSymbol('10.5', 'Rp', 'right'));
        $this->assertSame('$ 20', MoneyFormatter::withSymbol(10, '$', 'left', 2));
    }
}
