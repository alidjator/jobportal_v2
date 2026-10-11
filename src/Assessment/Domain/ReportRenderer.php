<?php

namespace Src\Assessment\Domain;

/**
 * Fills the placeholders of a report template. The draft takes the candidate, the test and the scale values; the signature takes the report number, the date
 * and the psychologist's name. A placeholder that is not filled yet stays as it is.
 */
final class ReportRenderer
{
    /** The placeholder shown for a scale whose raw score no norm covers. */
    public const string NOT_AVAILABLE = 'n/a';

    /**
     * @param  array<string, float>  $scaleScores
     * @param  list<Norm>  $norms
     */
    public static function draft(string $template, string $candidateName, string $testTitle, array $scaleScores, array $norms): string
    {
        $values = ['candidate_name' => $candidateName, 'test_title' => $testTitle];

        foreach ($scaleScores as $scale => $raw) {
            $norm = NormConverter::convert($norms, (string) $scale, $raw);
            $values["scale:{$scale}:raw"] = self::number($raw);
            $values["scale:{$scale}:standard"] = $norm === null ? self::NOT_AVAILABLE : self::number($norm->standardScore);
            $values["scale:{$scale}:band"] = $norm === null ? self::NOT_AVAILABLE : $norm->band;
        }

        return self::fill($template, $values);
    }

    public static function signed(string $body, string $number, string $signedOn, string $psychologistName): string
    {
        return self::fill($body, ['report_number' => $number, 'signed_on' => $signedOn, 'psychologist_name' => $psychologistName]);
    }

    public static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /** @param  array<string, string>  $values */
    private static function fill(string $text, array $values): string
    {
        return (string) preg_replace_callback('/\{\{(.*?)\}\}/s', static fn (array $match): string => $values[trim($match[1])] ?? $match[0], $text);
    }
}
