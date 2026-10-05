<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;

interface UserRepository
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * The accounts with these ids, keyed by id; an id without an account is missing from the result.
     *
     * @param  list<int>  $ids
     * @return array<int, User>
     */
    public function findByIds(array $ids): array;

    /**
     * Accounts whose deletion grace period has ended and that are not anonymised yet, oldest request first.
     *
     * @return list<User>
     */
    public function findDueForAnonymization(DateTimeImmutable $now, int $limit): array;

    /** Inserts a new user or updates an existing one, and returns the stored aggregate (with its id). */
    public function save(User $user): User;
}
