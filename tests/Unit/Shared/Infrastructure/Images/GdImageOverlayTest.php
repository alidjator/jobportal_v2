<?php

namespace Tests\Unit\Shared\Infrastructure\Images;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Infrastructure\Images\GdImageOverlay;

/** The strokes of a canvas laid over the sheet of a question: the size of the sheet, the strokes where they are, the sheet everywhere else. */
final class GdImageOverlayTest extends TestCase
{
    private function png(int $width, int $height, int $red, int $green, int $blue, int $alpha = 0, ?callable $draw = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, $red, $green, $blue, $alpha));

        if ($draw !== null) {
            $draw($image);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function colourAt(string $png, int $x, int $y): array
    {
        $image = imagecreatefromstring($png);
        $colour = imagecolorat($image, $x, $y);

        return [($colour >> 16) & 0xFF, ($colour >> 8) & 0xFF, $colour & 0xFF];
    }

    public function test_the_strokes_sit_over_the_sheet_at_the_size_of_the_sheet(): void
    {
        $sheet = $this->png(200, 100, 255, 255, 255);
        // The strokes are twice as large as the sheet: a red block in the left half, transparent elsewhere.
        $strokes = $this->png(400, 200, 0, 0, 0, 127, static function ($image): void {
            imagefilledrectangle($image, 0, 0, 199, 199, (int) imagecolorallocatealpha($image, 255, 0, 0, 0));
        });

        $result = (new GdImageOverlay)->overlay($sheet, $strokes);

        $this->assertSame([200, 100, 'image/png'], [$result->width, $result->height, $result->mimeType]);
        $this->assertSame([255, 0, 0], $this->colourAt($result->contents, 20, 50));
        $this->assertSame([255, 255, 255], $this->colourAt($result->contents, 180, 50));
    }

    public function test_something_that_is_not_an_image_is_refused(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        (new GdImageOverlay)->overlay($this->png(10, 10, 255, 255, 255), 'not an image');
    }
}
