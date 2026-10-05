<?php

namespace Src\Company\Application\Documents;

/** A stored document ready to be sent: its bytes, media type and the safe name a download gets (`<type-slug>.<extension>`, never the client's name). */
final readonly class DocumentFileContent
{
    public function __construct(
        public string $contents,
        public string $mimeType,
        public string $downloadName,
    ) {}
}
