<?php

namespace Src\Assessment\Application\Authoring;

/** A picture with its bytes, ready to be sent to the reader. */
final readonly class QuestionImageFile
{
    public function __construct(public string $contents, public string $mimeType) {}
}
