<?php

namespace Src\JobSeeker\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The checks of a resume file, made on its content (never on the name or the type the client sent): it must start as a PDF, and it must not be encrypted or
 * carry scripts, launch actions or embedded files. The last part is a simple scan of the file for those names, so it is a best-effort check: names inside
 * compressed parts of a PDF are not seen. It is not a malware scan.
 */
final class ResumePdf
{
    private const string SIGNATURE = '%PDF-';

    private const string FORBIDDEN = '/\/(Encrypt|JavaScript|JS|Launch|EmbeddedFile)(?![A-Za-z0-9])/';

    public const int NAME_MAX_LENGTH = 100;

    public const int RESUMES_MAX = 5;

    /**
     * @throws BusinessRuleViolation `invalid-resume`
     */
    public static function check(string $contents): void
    {
        if (! str_starts_with($contents, self::SIGNATURE)) {
            throw self::refused('The file must be a PDF.');
        }

        if (preg_match(self::FORBIDDEN, $contents) === 1) {
            throw self::refused('The PDF must not be encrypted or hold scripts, launch actions or embedded files.');
        }
    }

    /**
     * The name a resume starts with: the client's file name without a path or control characters, at most 100 characters; "resume" when nothing is left.
     */
    public static function cleanName(string $name): string
    {
        $name = mb_scrub($name);
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/\p{C}+/u', '', $name));

        return $name === '' ? 'resume' : mb_substr($name, 0, self::NAME_MAX_LENGTH);
    }

    private static function refused(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-resume');
    }
}
