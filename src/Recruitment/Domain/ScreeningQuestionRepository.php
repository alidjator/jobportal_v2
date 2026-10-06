<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface ScreeningQuestionRepository
{
    public function find(int $id): ?ScreeningQuestion;

    /**
     * The questions with these ids, with their choices, keyed by id; an id without a question is missing.
     *
     * @param  list<int>  $ids
     * @return array<int, ScreeningQuestion>
     */
    public function findMany(array $ids): array;

    /** Locks the question row until the transaction ends, so two changes of the same question run one after the other. */
    public function lock(int $id): void;

    /**
     * @param  list<string>  $labels  the choices in their order (empty unless the type has options)
     * @return int the id of the new question
     */
    public function create(int $companyId, QuestionType $type, string $text, array $labels, int $createdByUserId, DateTimeImmutable $at): int;

    public function updateText(int $id, string $text, DateTimeImmutable $at): void;

    /**
     * Makes the choices of the question the given list: an entry with an id renames that choice, one without an id is new, a choice that is not
     * listed is deleted; the order of the list is the order of the choices.
     *
     * @param  list<array{id: int|null, label: string}>  $options
     */
    public function replaceOptions(int $id, array $options, DateTimeImmutable $at): void;

    public function setArchived(int $id, ?DateTimeImmutable $archivedAt, DateTimeImmutable $at): void;

    public function delete(int $id): void;

    /** Whether any job has this question attached. */
    public function isAttached(int $id): bool;

    /**
     * The ids of the choices of this question that a job uses as an ideal answer.
     *
     * @return list<int>
     */
    public function idealOptionIds(int $id): array;
}
