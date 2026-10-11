<?php

namespace Src\Assessment\Domain;

interface QuestionRepository
{
    /** @return list<Question> by position, with their options */
    public function ofSection(int $sectionId): array;

    public function find(int $id): ?Question;

    /**
     * @param  list<int>  $ids
     * @return array<int, Question> id => question, with options
     */
    public function manyById(array $ids): array;

    /** Adds the question after the last one of its section and returns it with its id, position and options. */
    public function add(Question $question): Question;

    /** Replaces the question's fields and options. */
    public function update(Question $question): void;

    /** Removes the question and closes the gap in the positions. */
    public function delete(int $id): void;

    /**
     * @param  list<int>  $orderedIds  every question of the section, once
     */
    public function reorder(int $sectionId, array $orderedIds): void;

    public function countInSection(int $sectionId): int;

    /**
     * The number of questions of each section of the test.
     *
     * @return array<int, int> section id => questions
     */
    public function countsBySection(int $testId): array;

    /** True when another question of the test, of this type, is already a sample. */
    public function sampleTaken(int $testId, QuestionType $type, ?int $exceptQuestionId): bool;

    /**
     * The samples of a test, in order.
     *
     * @return list<Question>
     */
    public function samplesOfTest(int $testId): array;

    /**
     * The scales used by the questions and options of a test.
     *
     * @return list<string> sorted
     */
    public function scalesOfTest(int $testId): array;

    /** Whether any question of the test has the type. */
    public function testHasType(int $testId, QuestionType $type): bool;
}
