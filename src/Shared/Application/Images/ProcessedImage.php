<?php

namespace Src\Shared\Application\Images;

/** An image that was decoded and encoded again: clean bytes, and the extension and media type that go with them. */
final readonly class ProcessedImage
{
    public function __construct(
        public string $contents,
        public string $extension,
        public string $mimeType,
        public int $width,
        public int $height,
    ) {}
}
