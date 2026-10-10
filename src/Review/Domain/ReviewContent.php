<?php

namespace Src\Review\Domain;

/** What the author wrote: the overall rating (1 to 5), the optional aspect ratings, and the text (null once the author's account was erased). */
final readonly class ReviewContent
{
    public function __construct(
        public int $rating,
        public ?int $cultureRating,
        public ?int $payRating,
        public ?int $hiringRating,
        public ?string $title,
        public ?string $body,
    ) {}

    public function withoutText(): self
    {
        return new self($this->rating, $this->cultureRating, $this->payRating, $this->hiringRating, null, null);
    }
}
