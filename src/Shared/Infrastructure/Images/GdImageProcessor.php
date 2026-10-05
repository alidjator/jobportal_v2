<?php

namespace Src\Shared\Infrastructure\Images;

use GdImage;
use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Application\Images\ProcessedImage;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Throwable;

final class GdImageProcessor implements ImageProcessor
{
    /** Above this many pixels a source is refused before it is decoded: a small file can expand to gigabytes of memory (decompression bomb). */
    private const int MAX_SOURCE_PIXELS = 16_000_000;

    private const int JPEG_QUALITY = 85;

    private const array ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function process(string $contents, int $maxWidth, int $maxHeight): ProcessedImage
    {
        try {
            return $this->run($contents, $maxWidth, $maxHeight);
        } catch (BusinessRuleViolation $e) {
            throw $e;
        } catch (Throwable) {
            // GD raises warnings (turned into exceptions) for damaged files; to the client that is simply not a usable image.
            throw $this->invalid();
        }
    }

    private function run(string $contents, int $maxWidth, int $maxHeight): ProcessedImage
    {
        $info = getimagesizefromstring($contents);

        if ($info === false || ! in_array($info[2], self::ALLOWED_TYPES, true)) {
            throw $this->invalid();
        }

        [$width, $height] = $info;

        if ($width < 1 || $height < 1 || $width * $height > self::MAX_SOURCE_PIXELS) {
            throw $this->invalid();
        }

        $image = imagecreatefromstring($contents);

        if ($image === false) {
            throw $this->invalid();
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->applyOrientation($image, $contents);
        }

        $image = $this->fit($image, $maxWidth, $maxHeight);

        return $this->encode($image);
    }

    /** JPEG photos often carry a rotation in their metadata; it is applied now because the metadata is dropped. Mirrored variants are rare and left alone. */
    private function applyOrientation(GdImage $image, string $contents): GdImage
    {
        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            return $image;
        }

        fwrite($stream, $contents);
        rewind($stream);
        $exif = function_exists('exif_read_data') ? exif_read_data($stream) : false;
        fclose($stream);

        $angle = match (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }

    /** Shrinks the image to fit the box, keeping its proportions and its transparency. An image that already fits is left as it is. */
    private function fit(GdImage $image, int $maxWidth, int $maxHeight): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxWidth && $height <= $maxHeight) {
            return $image;
        }

        $scale = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, (int) imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function encode(GdImage $image): ProcessedImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        ob_start();

        if ($this->hasTransparency($image)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, null, 6);
            $extension = 'png';
            $mime = 'image/png';
        } else {
            imagejpeg($image, null, self::JPEG_QUALITY);
            $extension = 'jpg';
            $mime = 'image/jpeg';
        }

        $bytes = (string) ob_get_clean();

        if ($bytes === '') {
            throw $this->invalid();
        }

        return new ProcessedImage($bytes, $extension, $mime, $width, $height);
    }

    /** True when any pixel is not fully opaque. The image is already within the target size, so the scan stays small. */
    private function hasTransparency(GdImage $image): bool
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function invalid(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('The file is not a usable JPEG, PNG or WebP image.', 'invalid-image');
    }
}
