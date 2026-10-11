<?php

namespace Src\Assessment\Domain;

/**
 * The digits of one Kraepelin-type column and the answers they call for: the candidate adds each digit to the one below it and writes the last digit of the
 * sum. The digits come from the attempt's seed, so the server never stores them and gives the same column again after a reload.
 */
final class ColumnDigits
{
    /** @return list<int> */
    public static function of(int $seed, int $sectionId, int $column, int $count): array
    {
        $digits = [];
        $block = 0;

        while (count($digits) < $count) {
            foreach (str_split(hash('sha256', "{$seed}:{$sectionId}:{$column}:{$block}", true)) as $byte) {
                if (count($digits) === $count) {
                    break;
                }

                // Bytes above 249 are skipped so every digit is equally likely.
                if (ord($byte) < 250) {
                    $digits[] = ord($byte) % 10;
                }
            }

            $block++;
        }

        return $digits;
    }

    /**
     * The right answer of each pair of neighbouring digits.
     *
     * @param  list<int>  $digits
     * @return list<int>
     */
    public static function expected(array $digits): array
    {
        $answers = [];

        for ($i = 1; $i < count($digits); $i++) {
            $answers[] = ($digits[$i - 1] + $digits[$i]) % 10;
        }

        return $answers;
    }
}
