<?php

namespace Src\Company\Application\Images;

/** A stored company picture ready to be sent: its bytes, media type and version (the random part of its file name, which changes when the picture is replaced). */
final readonly class CompanyImageContent
{
    public function __construct(
        public string $contents,
        public string $mimeType,
        public string $version,
    ) {}
}
