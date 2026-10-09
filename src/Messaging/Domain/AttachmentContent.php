<?php

namespace Src\Messaging\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The checks of an attachment, made on its content (never on the name or the type the client sent): it must be a PDF, a JPEG or a PNG by its first bytes.
 * A PDF must not be encrypted or carry scripts, launch actions or embedded files; that part is a simple scan for those names, so it is a best-effort check
 * and not a malware scan.
 */
final class AttachmentContent
{
    private const string PDF_FORBIDDEN = '/\/(Encrypt|JavaScript|JS|Launch|EmbeddedFile)(?![A-Za-z0-9])/';

    /**
     * @return string the mime type read from the content
     *
     * @throws BusinessRuleViolation `invalid-attachment`
     */
    public static function mimeOf(string $contents): string
    {
        if (str_starts_with($contents, '%PDF-')) {
            if (preg_match(self::PDF_FORBIDDEN, $contents) === 1) {
                throw self::refused('A PDF must not be encrypted or hold scripts, launch actions or embedded files.');
            }

            return 'application/pdf';
        }

        if (str_starts_with($contents, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($contents, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        throw self::refused('An attachment must be a PDF, JPG or PNG file.');
    }

    /** The name an attachment starts with: the client's file name without a path or control characters, at most 100 characters; "file" when nothing is left. */
    public static function cleanName(string $name): string
    {
        $name = mb_scrub($name);
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/\p{C}+/u', '', $name));

        return $name === '' ? 'file' : mb_substr($name, 0, MessagingRules::ATTACHMENT_NAME_MAX_LENGTH);
    }

    public static function extensionOf(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            default => 'png',
        };
    }

    private static function refused(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-attachment');
    }
}
