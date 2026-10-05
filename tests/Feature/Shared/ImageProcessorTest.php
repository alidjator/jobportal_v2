<?php

namespace Tests\Feature\Shared;

use GdImage;
use Src\Shared\Application\Images\ImageProcessor;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Tests\FeatureTestCase;

final class ImageProcessorTest extends FeatureTestCase
{
    private ImageProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = $this->app->make(ImageProcessor::class);
    }

    private function canvas(int $width, int $height, bool $transparent): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 200, 30, 30, $transparent ? 64 : 0));

        return $image;
    }

    private function encoded(callable $encode): string
    {
        ob_start();
        $encode();

        return (string) ob_get_clean();
    }

    private function png(int $width = 40, int $height = 20, bool $transparent = false): string
    {
        $image = $this->canvas($width, $height, $transparent);

        return $this->encoded(fn () => imagepng($image));
    }

    private function jpeg(int $width = 40, int $height = 20): string
    {
        $image = $this->canvas($width, $height, false);

        return $this->encoded(fn () => imagejpeg($image, null, 90));
    }

    private function webp(int $width = 40, int $height = 20, bool $transparent = false): string
    {
        $image = $this->canvas($width, $height, $transparent);

        return $this->encoded(fn () => imagewebp($image, null, 90));
    }

    /** A JPEG with an EXIF block that carries the given orientation (and a GPS-looking text marker that must not survive). */
    private function jpegWithExif(int $orientation, int $width = 40, int $height = 20): string
    {
        $tiff = "II*\x00\x08\x00\x00\x00"."\x01\x00"."\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00"."\x00\x00\x00\x00";
        $exif = "Exif\x00\x00".$tiff.'GPSLatitude=-6.2088;GPSLongitude=106.8456';
        $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
        $jpeg = $this->jpeg($width, $height);

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    /** @return array{int, int} */
    private function dimensions(string $contents): array
    {
        $info = getimagesizefromstring($contents);
        $this->assertIsArray($info);

        return [$info[0], $info[1]];
    }

    private function assertInvalid(string $contents): void
    {
        try {
            $this->processor->process($contents, 512, 512);
            $this->fail('The content was accepted as an image.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('invalid-image', $e->errorCode());
        }
    }

    public function test_the_output_is_a_jpeg_without_transparency_and_a_png_with_it(): void
    {
        $this->assertSame('jpg', $this->processor->process($this->jpeg(), 512, 512)->extension);
        $this->assertSame('jpg', $this->processor->process($this->png(), 512, 512)->extension);
        $this->assertSame('jpg', $this->processor->process($this->webp(), 512, 512)->extension);
        $this->assertSame('png', $this->processor->process($this->png(40, 20, true), 512, 512)->extension);
        $this->assertSame('png', $this->processor->process($this->webp(40, 20, true), 512, 512)->extension);

        $jpeg = $this->processor->process($this->jpeg(), 512, 512);
        $this->assertSame('image/jpeg', $jpeg->mimeType);
        $this->assertSame('image/png', $this->processor->process($this->png(40, 20, true), 512, 512)->mimeType);
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($jpeg->contents)[2]);
    }

    public function test_transparency_survives_in_the_png(): void
    {
        $result = $this->processor->process($this->png(40, 20, true), 512, 512);

        $image = imagecreatefromstring($result->contents);
        $this->assertNotFalse($image);
        $this->assertGreaterThan(0, (imagecolorat($image, 5, 5) >> 24) & 0x7F);
    }

    public function test_a_large_image_is_shrunk_to_fit_keeping_its_proportions(): void
    {
        $logo = $this->processor->process($this->png(2000, 1000), 512, 512);
        $this->assertSame([512, 256], $this->dimensions($logo->contents));
        $this->assertSame(512, $logo->width);
        $this->assertSame(256, $logo->height);

        $banner = $this->processor->process($this->jpeg(3000, 1200), 1600, 600);
        $this->assertSame([1500, 600], $this->dimensions($banner->contents));

        $tall = $this->processor->process($this->jpeg(400, 1600), 512, 512);
        $this->assertSame([128, 512], $this->dimensions($tall->contents));
    }

    public function test_a_small_image_is_not_enlarged(): void
    {
        $result = $this->processor->process($this->png(100, 50), 512, 512);

        $this->assertSame([100, 50], $this->dimensions($result->contents));
    }

    public function test_an_image_exactly_at_the_limit_is_left_at_its_size(): void
    {
        $this->assertSame([512, 512], $this->dimensions($this->processor->process($this->png(512, 512), 512, 512)->contents));
        $this->assertSame([1600, 600], $this->dimensions($this->processor->process($this->jpeg(1600, 600), 1600, 600)->contents));
    }

    public function test_metadata_is_dropped(): void
    {
        $source = $this->jpegWithExif(1);
        $this->assertStringContainsString('GPSLatitude', $source);

        $result = $this->processor->process($source, 512, 512);

        $this->assertStringNotContainsString('GPSLatitude', $result->contents);
        $this->assertStringNotContainsString('Exif', $result->contents);
    }

    public function test_the_rotation_in_the_metadata_is_applied_before_it_is_dropped(): void
    {
        $this->assertSame([20, 40], $this->dimensions($this->processor->process($this->jpegWithExif(6), 512, 512)->contents));
        $this->assertSame([20, 40], $this->dimensions($this->processor->process($this->jpegWithExif(8), 512, 512)->contents));
        $this->assertSame([40, 20], $this->dimensions($this->processor->process($this->jpegWithExif(3), 512, 512)->contents));
        $this->assertSame([40, 20], $this->dimensions($this->processor->process($this->jpegWithExif(1), 512, 512)->contents));
    }

    public function test_something_that_is_not_an_allowed_image_is_refused(): void
    {
        $gif = $this->encoded(fn () => imagegif($this->canvas(10, 10, false)));

        $this->assertInvalid('');
        $this->assertInvalid('just some text');
        $this->assertInvalid('<?php echo 1;');
        $this->assertInvalid('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->assertInvalid($gif);
        $this->assertInvalid("GIF89a<?php system('id'); ?>");
        $this->assertInvalid('%PDF-1.4 fake');
    }

    public function test_a_damaged_image_is_refused(): void
    {
        $png = $this->png(200, 200);

        $this->assertInvalid(substr($png, 0, 60));
        $this->assertInvalid(substr($png, 0, (int) (strlen($png) / 2)));
        $this->assertInvalid(substr($this->jpeg(200, 200), 0, 300));
    }

    public function test_code_appended_to_a_real_image_does_not_survive(): void
    {
        $result = $this->processor->process($this->png().'<?php system($_GET["c"]); ?>', 512, 512);

        $this->assertStringNotContainsString('<?php', $result->contents);
        $this->assertStringNotContainsString('system(', $result->contents);
    }

    public function test_a_source_with_too_many_pixels_is_refused_before_it_is_decoded(): void
    {
        $header = pack('N', 5000).pack('N', 5000)."\x08\x06\x00\x00\x00";
        $ihdr = 'IHDR'.$header;
        $png = "\x89PNG\r\n\x1a\n".pack('N', strlen($header)).$ihdr.pack('N', crc32($ihdr));

        $this->assertSame([5000, 5000], $this->dimensions($png));
        $this->assertInvalid($png);
    }
}
