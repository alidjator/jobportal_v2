<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

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

    /**
     * The ids of the active, non-anonymised job seeker accounts with a verified email whose first sign-in was at or before the cutoff, ascending, after the cursor.
     *
     * @return list<int>
     */
    public function findJobSeekerIdsFirstSignedInBefore(DateTimeImmutable $cutoff, int $afterId, int $limit): array;

    /**
     * The accounts that are not anonymised, newest first; the search text matches part of the name or the email.
     *
     * @return Page<User>
     */
    public function search(?string $search, PageRequest $request): Page;

    /**
     * Active accounts of the public sign-in (not psychologists, not anonymised) that have no password and no link to a social provider, so
     * they can only get in through a password reset; ascending id after the cursor.
     *
     * @return list<User>
     */
    public function findWithoutPassword(int $afterId, int $limit): array;

    /** Inserts a new user or updates an existing one, and returns the stored aggregate (with its id). */
    public function save(User $user): User;
}
