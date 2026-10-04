<?php

namespace Src\Platform\Application\Email;

use Src\Shared\Application\Email\EmailPlaceholders;
use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What an admin's subject and body must satisfy: not empty, subject on one line and short, only the template's own variables, and
 * every required variable (such as the verification code) present in the body. Problems are reported per field in `errors`.
 */
final class EmailTemplateRules
{
    public const int MAX_SUBJECT_LENGTH = 255;

    public const int MAX_BODY_LENGTH = 20000;

    /** @throws BusinessRuleViolation `invalid-email-template` with `errors` per field */
    public static function assertValid(EmailTemplateDefinition $definition, string $subject, string $body): void
    {
        $errors = [];

        $subject = trim($subject);
        $body = trim($body);

        if ($subject === '') {
            $errors['subject'][] = 'The subject is required.';
        } elseif (mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            $errors['subject'][] = 'The subject may be at most '.self::MAX_SUBJECT_LENGTH.' characters.';
        } elseif (preg_match('/[\r\n]/', $subject) === 1) {
            $errors['subject'][] = 'The subject must be on one line.';
        }

        if ($body === '') {
            $errors['body'][] = 'The body is required.';
        } elseif (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            $errors['body'][] = 'The body may be at most '.self::MAX_BODY_LENGTH.' characters.';
        }

        $allowed = $definition->variableNames();

        foreach (['subject' => $subject, 'body' => $body] as $field => $text) {
            $unknown = array_values(array_diff(EmailPlaceholders::in($text), $allowed));

            if ($unknown !== []) {
                $errors[$field][] = 'Unknown variable(s): '.implode(', ', array_map(fn (string $name): string => '{{'.$name.'}}', $unknown)).'. Allowed: '
                    .implode(', ', array_map(fn (string $name): string => '{{'.$name.'}}', $allowed)).'.';
            }
        }

        $missing = array_values(array_diff($definition->requiredVariableNames(), EmailPlaceholders::in($body)));

        if ($missing !== [] && $body !== '') {
            $errors['body'][] = 'The body must contain: '.implode(', ', array_map(fn (string $name): string => '{{'.$name.'}}', $missing)).'.';
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('The email template is not valid.', 'invalid-email-template', extensions: ['errors' => $errors]);
        }
    }
}
