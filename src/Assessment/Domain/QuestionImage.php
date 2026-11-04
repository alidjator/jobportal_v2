<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/**
 * A picture of a question or of one of its options (`docs/design/phase-8-question-images.md`). It belongs to a test; `questionId` is null until a question names it. `optionPosition` is null for the question's own
 * pictures (then `position` is their order) and the option's position (from 1) for an option's picture. `path` is the file on the private disk and is never shown to a reader.
 */
final readonly class QuestionImage
{
    public function __construct(
        public ?int $id,
        public int $testId,
        public ?int $questionId,
        public ?int $optionPosition,
        public int $position,
        public string $path,
        public string $contentType,
        public int $sizeBytes,
        public int $width,
        public int $height,
        public string $alt,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public function placedAt(int $questionId, ?int $optionPosition, int $position): self
    {
        return new self($this->id, $this->testId, $questionId, $optionPosition, $position, $this->path, $this->contentType, $this->sizeBytes, $this->width, $this->height, $this->alt, $this->createdAt);
    }

    /** A copy for another question of another test, with its own file. */
    public function copiedTo(int $testId, int $questionId, string $path): self
    {
        return new self(null, $testId, $questionId, $this->optionPosition, $this->position, $path, $this->contentType, $this->sizeBytes, $this->width, $this->height, $this->alt);
    }
}
