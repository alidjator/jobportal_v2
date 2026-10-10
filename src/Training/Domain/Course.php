<?php

namespace Src\Training\Domain;

use DateTimeImmutable;

/** A course of the training platform. `summary` is plain text; a date is null when the platform has none; `url` opens the course on the platform. */
final readonly class Course
{
    public function __construct(
        public int $id,
        public string $name,
        public string $summary,
        public ?string $imageUrl,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public string $url,
    ) {}

    public function withImage(string $imageUrl): self
    {
        return new self($this->id, $this->name, $this->summary, $imageUrl, $this->startsAt, $this->endsAt, $this->url);
    }
}
