<?php

namespace Src\Shared\Application;

/** A stored video ready to be streamed: the caller reads and closes the stream. */
final readonly class StreamedFile
{
    /** @param  resource  $stream */
    public function __construct(public mixed $stream, public int $size, public string $mimeType, public string $name) {}
}
