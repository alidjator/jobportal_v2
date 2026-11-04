<?php

namespace Src\Shared\Infrastructure\Images;

use GdImage;
use Src\Shared\Application\Images\ImageOverlay;
use Src\Shared\Application\Images\ProcessedImage;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Throwable;

final class GdImageOverlay implements ImageOverlay
{
    /** Above this many pixels a source is refused before it is decoded (see {@see GdImageProcessor}). */
    private const int MAX_SOURCE_PIXELS = 16_000_000;

    public function overlay(string $base, string $top): ProcessedImage
    {
        try {
            $below = $this->decode($base);
            $above = $this->decode($top);

            $width = imagesx($below);
            $height = imagesy($below);
            $canvas = imagecreatetruecolor($width, $height);

            imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
            imagealphablending($canvas, true);
            imagecopy($canvas, $below, 0, 0, 0, 0, $width, $height);
            imagecopyresampled($canvas, $above, 0, 0, 0, 0, $width, $height, imagesx($above), imagesy($above));

            ob_start();
            imagepng($canvas, null, 6);
            $bytes = (string) ob_get_clean();
        } catch (BusinessRuleViolation $e) {
            throw $e;
        } catch (Throwable) {
            throw $this->invalid();
        }

        if ($bytes === '') {
            throw $this->invalid();
        }

        return new ProcessedImage($bytes, 'png', 'image/png', $width, $height);
    }

    private function decode(string $contents): GdImage
    {
        $info = getimagesizefromstring($contents);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            throw $this->invalid();
        }

        return imagecreatefromstring($contents) ?: throw $this->invalid();
    }

    private function invalid(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('The file is not a usable JPEG, PNG or WebP image.', 'invalid-image');
    }
}
