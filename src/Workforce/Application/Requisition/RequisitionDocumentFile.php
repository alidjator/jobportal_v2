<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The file attached to a requisition: a PDF, a Word or Excel file without macros, or a JPEG or PNG picture, of at most 5 MB, checked by its content and not by what the browser says.
 * A PDF may carry no scripts, launch actions or embedded files.
 */
final class RequisitionDocumentFile
{
    public const int MAX_BYTES = 5 * 1024 * 1024;

    public const int MAX_PER_REQUISITION = 5;

    private const string FORBIDDEN = '/\/(Encrypt|JavaScript|JS|Launch|EmbeddedFile)(?![A-Za-z0-9])/';

    private const array TYPES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /**
     * @return string the content type to keep and to send back
     *
     * @throws BusinessRuleViolation `invalid-file`
     */
    public static function check(string $contents, string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! isset(self::TYPES[$extension]) || $contents === '' || strlen($contents) > self::MAX_BYTES || ! self::matches($extension, $contents)) {
            throw new BusinessRuleViolation(
                'The file must be a PDF, a Word or Excel file without macros, or a JPEG or PNG picture of at most 5 MB.', 'invalid-file',
                extensions: ['errors' => ['file' => ['The file must be a PDF, a Word or Excel file without macros, or a JPEG or PNG picture of at most 5 MB.']]],
            );
        }

        return self::TYPES[$extension];
    }

    /** The file name as it is kept: without a path or control characters, at most 100 characters. */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', mb_scrub($name)));
        $name = trim((string) preg_replace('/\p{C}+/u', '', $name));

        return $name === '' ? 'document' : mb_substr($name, -100);
    }

    public static function extension(string $contentType): string
    {
        return match ($contentType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            self::TYPES['docx'] => 'docx',
            default => 'xlsx',
        };
    }

    private static function matches(string $extension, string $contents): bool
    {
        return match ($extension) {
            'pdf' => str_starts_with($contents, '%PDF-') && preg_match(self::FORBIDDEN, $contents) !== 1,
            'jpg', 'jpeg' => str_starts_with($contents, "\xFF\xD8\xFF"),
            'png' => str_starts_with($contents, "\x89PNG\r\n\x1A\n"),
            'docx' => self::office($contents, 'word/'),
            default => self::office($contents, 'xl/'),
        };
    }

    /** An Office Open XML package (a ZIP) with the folder of its kind, and no macro project. */
    private static function office(string $contents, string $folder): bool
    {
        return str_starts_with($contents, "PK\x03\x04") && str_contains($contents, '[Content_Types].xml') && str_contains($contents, $folder) && ! str_contains($contents, 'vbaProject.bin');
    }
}
