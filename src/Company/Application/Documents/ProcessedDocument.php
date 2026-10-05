<?php

namespace Src\Company\Application\Documents;

/** A document file that passed the checks: the bytes to store, and the extension and media type that go with them. */
final readonly class ProcessedDocument
{
    public function __construct(
        public string $contents,
        public string $extension,
        public string $mimeType,
    ) {}
}
