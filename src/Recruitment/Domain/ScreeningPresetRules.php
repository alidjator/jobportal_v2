<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/** The content limits of a screening preset: its name. */
final class ScreeningPresetRules
{
    public const int NAME_MIN = 1;

    public const int NAME_MAX = 100;

    /**
     * @throws BusinessRuleViolation `invalid-screening-preset` with `errors` on `name`
     */
    public static function name(string $name): string
    {
        $name = trim($name);
        $length = mb_strlen($name);

        if ($length < self::NAME_MIN || $length > self::NAME_MAX) {
            throw self::invalid('name', new ErrorMessage('The name must have between :min and :max characters.', ['min' => self::NAME_MIN, 'max' => self::NAME_MAX]));
        }

        return $name;
    }

    public static function invalid(string $field, string|ErrorMessage $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation('The screening preset is not valid.', 'invalid-screening-preset', extensions: ['errors' => [$field => [$message]]]);
    }
}
