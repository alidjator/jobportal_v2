<?php

namespace Src\Assessment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The text of a psychologist's report with placeholders in double braces. The report is rendered when it is drafted (part 6) from these:
 * `{{candidate_name}}`, `{{test_title}}`, `{{report_number}}`, `{{signed_on}}`, `{{psychologist_name}}` and, per scale, `{{scale:KEY:raw}}`,
 * `{{scale:KEY:standard}}` and `{{scale:KEY:band}}`.
 */
final class ReportTemplateRules
{
    public const int BODY_MAX = 20000;

    public const array FIXED_PLACEHOLDERS = ['candidate_name', 'test_title', 'report_number', 'signed_on', 'psychologist_name'];

    public const array SCALE_VALUES = ['raw', 'standard', 'band'];

    /**
     * @param  list<string>  $knownScales  the scales the questions of the test use
     *
     * @throws BusinessRuleViolation `invalid-report-template` with `errors` on `body`
     */
    public static function check(string $body, array $knownScales): string
    {
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > self::BODY_MAX) {
            throw self::invalid('The report template must have between 1 and '.self::BODY_MAX.' characters.');
        }

        preg_match_all('/\{\{(.*?)\}\}/s', $body, $matches);

        foreach ($matches[1] as $token) {
            $token = trim($token);

            if (in_array($token, self::FIXED_PLACEHOLDERS, true)) {
                continue;
            }

            $parts = explode(':', $token);

            if (count($parts) !== 3 || $parts[0] !== 'scale' || ! in_array($parts[2], self::SCALE_VALUES, true)) {
                throw self::invalid("The placeholder {{{$token}}} is not known.");
            }

            if (! in_array($parts[1], $knownScales, true)) {
                throw self::invalid("No question of this test uses the scale {$parts[1]}.");
            }
        }

        return $body;
    }

    private static function invalid(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-report-template', extensions: ['errors' => ['body' => [$message]]]);
    }
}
