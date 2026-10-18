<?php

namespace Src\Shared\Infrastructure\Email;

use Illuminate\Mail\Mailables\Headers;

/**
 * Marks a message that is sent even to a suppressed address: the ones the person needs to use or protect the account (verification code, password reset, two-factor
 * required, account suspension). A mailable returns `EssentialEmail::headers()` from its `headers()` method; the suppression listener removes the header before sending.
 */
final class EssentialEmail
{
    public const string HEADER = 'X-Essential';

    public static function headers(): Headers
    {
        return new Headers(text: [self::HEADER => '1']);
    }
}
