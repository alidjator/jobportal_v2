<?php

namespace Src\Assessment\Domain;

interface SectionRepository
{
    /** @return list<Section> by position */
    public function ofTest(int $testId): array;

    public function find(int $id): ?Section;

    /** Adds the section after the last one of its test and returns it with its id and position. */
    public function add(Section $section): Section;

    public function update(Section $section): void;

    /** Removes the section with its questions and closes the gap in the positions. */
    public function delete(int $id): void;

    /**
     * Puts the sections in the given order.
     *
     * @param  list<int>  $orderedIds  every section of the test, once
     */
    public function reorder(int $testId, array $orderedIds): void;
}
