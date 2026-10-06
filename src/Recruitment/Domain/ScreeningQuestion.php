<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/** A question of a company's library. The flags that decide how a job uses it (required, must-have, ideal answer) belong to the job, not here. */
final readonly class ScreeningQuestion
{
    /** @param  list<QuestionOption>  $options  in their order */
    public function __construct(
        public int $id,
        public int $companyId,
        public QuestionType $type,
        public string $text,
        public array $options,
        public ?DateTimeImmutable $archivedAt,
        public int $createdByUserId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /** @return list<int> */
    public function optionIds(): array
    {
        return array_map(static fn (QuestionOption $option): int => $option->id, $this->options);
    }
}
