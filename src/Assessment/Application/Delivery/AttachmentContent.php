<?php

namespace Src\Assessment\Application\Delivery;

use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Checks the file of an invitation by its content, never by its name or the type the client sent: a PDF (it starts with the PDF signature) is kept as it is; a
 * JPEG or PNG is decoded and encoded again, so only the picture remains.
 */
final readonly class AttachmentContent
{
    private const string PDF_SIGNATURE = '%PDF-';

    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private const string JPEG_SIGNATURE = "\xFF\xD8\xFF";

    private const int MAX_IMAGE_SIDE = 3508;

    public function __construct(private ImageProcessor $images) {}

    /**
     * @return array{contents: string, extension: string, mime_type: string}
     *
     * @throws BusinessRuleViolation `invalid-attachment`
     */
    public function process(string $contents): array
    {
        if (str_starts_with($contents, self::PDF_SIGNATURE)) {
            return ['contents' => $contents, 'extension' => 'pdf', 'mime_type' => 'application/pdf'];
        }

        if (str_starts_with($contents, self::PNG_SIGNATURE) || str_starts_with($contents, self::JPEG_SIGNATURE)) {
            try {
                $image = $this->images->process($contents, self::MAX_IMAGE_SIDE, self::MAX_IMAGE_SIDE);

                return ['contents' => $image->contents, 'extension' => $image->extension, 'mime_type' => $image->mimeType];
            } catch (BusinessRuleViolation) {
                // Falls through to the same refusal: the picture could not be read.
            }
        }

        throw new BusinessRuleViolation('The file must be a PDF, a JPEG or a PNG.', 'invalid-attachment', extensions: ['errors' => ['file' => ['The file must be a PDF, a JPEG or a PNG.']]]);
    }
}
