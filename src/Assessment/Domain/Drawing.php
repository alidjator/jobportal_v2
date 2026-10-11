<?php

namespace Src\Assessment\Domain;

/** What a candidate drew for a drawing question, with the score and the note the provider's psychologist gave it. */
final readonly class Drawing
{
    public function __construct(
        public int $questionId,
        public string $path,
        public DrawingSource $source,
        public string $mimeType,
        public int $sizeBytes,
        public ?float $score = null,
        public ?string $note = null,
    ) {}
}
