<?php

namespace Src\Assessment\Application\Review;

/** The bytes of a candidate's drawing and its media type. */
final readonly class DrawingFile
{
    public function __construct(public string $contents, public string $mimeType) {}
}
