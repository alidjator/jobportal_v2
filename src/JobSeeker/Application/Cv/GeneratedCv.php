<?php

namespace Src\JobSeeker\Application\Cv;

/** A generated CV: the file name to offer and the bytes of the PDF. */
final readonly class GeneratedCv
{
    public function __construct(public string $name, public string $contents) {}
}
