<?php

namespace Src\Shared\Application\Images;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Lays one image over another, for the drawing a candidate made on the printed sheet of a question (`docs/design/phase-8-question-images.md` D12). */
interface ImageOverlay
{
    /**
     * Lays `$top` (an image with transparency, such as the strokes of a canvas) over `$base`, stretched to the size of the base. The result has the size of the base and is a PNG.
     *
     * @throws BusinessRuleViolation `invalid-image` when either is not a usable JPEG, PNG or WebP image
     */
    public function overlay(string $base, string $top): ProcessedImage;
}
