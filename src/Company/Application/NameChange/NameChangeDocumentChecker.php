<?php

namespace Src\Company\Application\NameChange;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Checks the proof of a name change by its content, never by the file name or the type the client sent: a PDF of at most 10 MB. */
final readonly class NameChangeDocumentChecker
{
    public const int MAX_KB = 10240;

    private const string PDF_SIGNATURE = '%PDF-';

    /**
     * @throws BusinessRuleViolation `file-too-large` or `invalid-document`
     */
    public function check(string $contents): void
    {
        if (strlen($contents) > self::MAX_KB * 1024) {
            throw new BusinessRuleViolation('The file is larger than the allowed :max KB.', 'file-too-large', extensions: ['max_size_kb' => self::MAX_KB], parameters: ['max' => self::MAX_KB]);
        }

        if (! str_starts_with($contents, self::PDF_SIGNATURE)) {
            throw new BusinessRuleViolation('The document must be a PDF file.', 'invalid-document');
        }
    }
}
