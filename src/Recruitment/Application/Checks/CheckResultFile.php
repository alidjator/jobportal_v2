<?php

namespace Src\Recruitment\Application\Checks;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The file a member attaches to the result of a check (not a medical one): a PDF of at most 10 MB, checked by its content, without scripts, launch actions or embedded files. Team-only. */
final class CheckResultFile
{
    public const int MAX_BYTES = 10 * 1024 * 1024;

    private const string FORBIDDEN = '/\/(Encrypt|JavaScript|JS|Launch|EmbeddedFile)(?![A-Za-z0-9])/';

    /** @throws BusinessRuleViolation `invalid-file` */
    public static function check(string $contents): void
    {
        if ($contents === '' || strlen($contents) > self::MAX_BYTES || ! str_starts_with($contents, '%PDF-') || preg_match(self::FORBIDDEN, $contents) === 1) {
            throw new BusinessRuleViolation('The file must be a PDF of at most 10 MB, without scripts or embedded files.', 'invalid-file');
        }
    }

    /** The file name as it is kept: without a path or control characters, at most 100 characters. */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', mb_scrub($name)));
        $name = trim((string) preg_replace('/\p{C}+/u', '', $name));

        return $name === '' ? 'result.pdf' : mb_substr($name, 0, 100);
    }
}
