<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

interface AttemptRepository
{
    public function find(int $id, bool $forUpdate = false): ?Attempt;

    public function findByInvitation(int $invitationId, bool $forUpdate = false): ?Attempt;

    /** Stores the new attempt with the questions drawn for it and returns it with its id. @param  list<AttemptQuestion>  $drawn */
    public function add(Attempt $attempt, array $drawn): Attempt;

    public function update(Attempt $attempt): void;

    /** @return list<AttemptQuestion> in the order they are asked */
    public function drawnOf(int $attemptId): array;

    /** @return list<int> the ids of the attempts that were not submitted and whose last section has closed by `$now` */
    public function idsOutOfTime(DateTimeImmutable $now): array;
}
