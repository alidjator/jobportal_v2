<?php

namespace Src\Identity\Domain;

/** An email address shown in part, so a person can recognise their own address and an audit entry never holds a whole one: `s***@example.test`. */
final class MaskedEmail
{
    public static function of(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return '***';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }
}
