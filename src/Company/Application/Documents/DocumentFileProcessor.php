<?php

namespace Src\Company\Application\Documents;

use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Checks a verification document by its content (never the file name or the type the client sent). A PDF is accepted when it starts with the
 * PDF signature and is stored as uploaded. Anything else must be a JPEG, PNG or WebP image, which is encoded again (all metadata dropped) and shrunk
 * only when its longest side is above 3508 px (A4 at 300 dpi), so a scan stays readable.
 */
final readonly class DocumentFileProcessor
{
    private const string PDF_SIGNATURE = '%PDF-';

    private const int MAX_IMAGE_SIDE = 3508;

    public function __construct(private ImageProcessor $images) {}

    /**
     * @throws BusinessRuleViolation `invalid-document` when the file is neither a PDF nor a usable JPEG, PNG or WebP image
     */
    public function process(string $contents): ProcessedDocument
    {
        if (str_starts_with($contents, self::PDF_SIGNATURE)) {
            return new ProcessedDocument($contents, 'pdf', 'application/pdf');
        }

        try {
            $image = $this->images->process($contents, self::MAX_IMAGE_SIDE, self::MAX_IMAGE_SIDE);
        } catch (BusinessRuleViolation) {
            throw new BusinessRuleViolation('The file must be a PDF or a JPEG, PNG or WebP image.', 'invalid-document');
        }

        return new ProcessedDocument($image->contents, $image->extension, $image->mimeType);
    }
}
