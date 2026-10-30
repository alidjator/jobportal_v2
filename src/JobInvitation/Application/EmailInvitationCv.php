<?php

namespace Src\JobInvitation\Application;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The CV a member attaches to an invitation by email: a PDF of at most 5 MB, checked by its content (it must start as a PDF and hold no scripts, launch actions or embedded files). */
final class EmailInvitationCv
{
    public const int MAX_BYTES = 5 * 1024 * 1024;

    private const string FORBIDDEN = '/\/(Encrypt|JavaScript|JS|Launch|EmbeddedFile)(?![A-Za-z0-9])/';

    /** @throws BusinessRuleViolation `invalid-file` */
    public static function check(string $contents): void
    {
        if ($contents === '' || strlen($contents) > self::MAX_BYTES || ! str_starts_with($contents, '%PDF-') || preg_match(self::FORBIDDEN, $contents) === 1) {
            throw new BusinessRuleViolation('The CV must be a PDF of at most 5 MB, without scripts or embedded files.', 'invalid-file');
        }
    }

    /** The file name as it is kept: without a path or control characters, at most 100 characters. */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', mb_scrub($name)));
        $name = trim((string) preg_replace('/\p{C}+/u', '', $name));

        return $name === '' ? 'cv.pdf' : mb_substr($name, 0, 100);
    }
}
