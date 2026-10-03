<?php

namespace Src\Billing\Domain;

/**
 * Format uang murni (tanpa session). Padanan helper lama `currencyAmountShort` dan `currencyPosition`.
 */
final class MoneyFormatter
{
    /** 1500 -> "2K", 2_500_000 -> "3M" (dibulatkan seperti helper lama). */
    public static function short(float|int|string $amount, float $rate = 1.0): string
    {
        $units = ['', 'K', 'M', 'B', 'T'];
        $num = (float) $amount * $rate;
        $i = 0;
        while ($num >= 1000 && $i < count($units) - 1) {
            $num /= 1000;
            $i++;
        }

        return round($num, 0).$units[$i];
    }

    /** Simbol di kiri ("$ 10") atau kanan ("10 $") sesuai `$position`. */
    public static function withSymbol(float|int|string $amount, string $symbol, string $position = 'left', float $rate = 1.0): string
    {
        $value = round((float) $amount * $rate, 2);

        return $position === 'left' ? $symbol.' '.$value : $value.' '.$symbol;
    }
}
