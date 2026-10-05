<?php

namespace Src\Shared\Application\Images;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Turns an uploaded image into a safe one: the type is read from the content (JPEG, PNG or WebP), the image is shrunk to fit the given
 * size (never enlarged or cropped) and encoded again, which drops every piece of metadata and anything hidden in the file. Used for
 * every image the API stores (company logos and banners, later avatars).
 */
interface ImageProcessor
{
    /**
     * The result is a PNG when the image has any transparency and a JPEG otherwise.
     *
     * @throws BusinessRuleViolation `invalid-image` when the content is not a JPEG, PNG or WebP image, is damaged, or is too large to decode safely
     */
    public function process(string $contents, int $maxWidth, int $maxHeight): ProcessedImage;
}
