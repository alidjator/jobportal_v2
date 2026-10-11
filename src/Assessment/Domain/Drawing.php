<?php

namespace Src\Assessment\Domain;

final readonly class Drawing
{
    public function __construct(
        public int $questionId,
        public string $path,
        public DrawingSource $source,
        public string $mimeType,
        public int $sizeBytes,
    ) {}
}
