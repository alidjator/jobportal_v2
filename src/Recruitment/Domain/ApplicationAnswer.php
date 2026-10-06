<?php

namespace Src\Recruitment\Domain;

/** One checked answer of an application. Only the field of the question's type is set (the chosen option ids for the two choice types). */
final readonly class ApplicationAnswer
{
    /** @param  list<int>  $optionIds */
    public function __construct(
        public int $questionId,
        public ?string $text = null,
        public ?int $number = null,
        public ?bool $bool = null,
        public array $optionIds = [],
    ) {}
}
