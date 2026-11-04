<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

interface QuestionImageRepository
{
    public function create(QuestionImage $image): QuestionImage;

    public function find(int $id): ?QuestionImage;

    /**
     * @param  list<int>  $ids
     * @return array<int, QuestionImage> by id
     */
    public function manyById(array $ids): array;

    /** @return list<QuestionImage> the images attached to the question, its own first by order, then the options' */
    public function ofQuestion(int $questionId): array;

    /**
     * @param  list<int>  $questionIds
     * @return list<QuestionImage> the images attached to any of the questions
     */
    public function ofQuestions(array $questionIds): array;

    /** @return list<QuestionImage> every image of the test, attached or not */
    public function ofTest(int $testId): array;

    /** @return list<QuestionImage> the images of the questions of the section */
    public function ofSection(int $sectionId): array;

    /** Lets go of every image of the question (they stay on the test), so the places can be filled again. */
    public function detachFrom(int $questionId): void;

    public function attach(int $imageId, int $questionId, ?int $optionPosition, int $position): void;

    /** @param  list<int>  $ids */
    public function delete(array $ids): void;

    /** @return list<QuestionImage> images no question names, made before the moment */
    public function unattachedBefore(DateTimeImmutable $moment, int $limit): array;
}
