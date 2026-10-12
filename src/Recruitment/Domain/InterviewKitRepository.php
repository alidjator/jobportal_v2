<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\StateConflict;

interface InterviewKitRepository
{
    /** The kit with its questions in order, or null. */
    public function find(int $kitId): ?InterviewKit;

    /**
     * The company's kits with their questions, by name.
     *
     * @return list<InterviewKit>
     */
    public function forCompany(int $companyId): array;

    public function countForCompany(int $companyId): int;

    /**
     * @return int the id of the new kit
     *
     * @throws StateConflict `kit-name-taken`
     */
    public function add(InterviewKit $kit): int;

    /**
     * Saves the name and replaces the whole list of questions.
     *
     * @throws StateConflict `kit-name-taken`
     */
    public function replace(InterviewKit $kit): void;

    /** Deletes the kit; interviews that were scheduled with it keep their copy, and the job defaults that named it go. */
    public function delete(int $kitId): void;
}
