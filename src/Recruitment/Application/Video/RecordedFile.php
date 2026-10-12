<?php

namespace Src\Recruitment\Application\Video;

/** A stored recording ready to be streamed: the caller reads and closes the stream. */
final readonly class RecordedFile
{
    /** @param  resource  $stream */
    public function __construct(public mixed $stream, public int $size, public string $mimeType, public string $name) {}
}
