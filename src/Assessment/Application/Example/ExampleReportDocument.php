<?php

namespace Src\Assessment\Application\Example;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Checks the example report by its content, never by its name or the type the client sent: it must be a PDF (it starts with the PDF signature) of at most 10 MB. */
final class ExampleReportDocument
{
    public const int MAX_MEGABYTES = 10;

    private const string PDF_SIGNATURE = '%PDF-';

    private const int MAX_NAME_LENGTH = 255;

    /** @throws BusinessRuleViolation `file-too-large` or `invalid-example-report` */
    public static function assertValid(string $contents): void
    {
        if (strlen($contents) > self::MAX_MEGABYTES * 1024 * 1024) {
            throw new BusinessRuleViolation('The file is larger than the allowed :max MB.', 'file-too-large', extensions: ['max_size_mb' => self::MAX_MEGABYTES], parameters: ['max' => self::MAX_MEGABYTES]);
        }

        if (! str_starts_with($contents, self::PDF_SIGNATURE)) {
            throw new BusinessRuleViolation('The example report must be a PDF.', 'invalid-example-report', extensions: ['errors' => ['file' => ['The example report must be a PDF.']]]);
        }
    }

    /** The client's name is kept for display only: no path, no control characters, at most 255 characters, and the extension `.pdf`. */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', mb_scrub($name)));
        $name = trim((string) preg_replace('/\p{C}+/u', '', $name));
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $stem = $stem === '' ? 'example-report' : $stem;

        return mb_substr($stem, 0, self::MAX_NAME_LENGTH - 4).'.pdf';
    }
}
