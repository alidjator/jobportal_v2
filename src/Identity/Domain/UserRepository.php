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
     * The ids among these that belong to imported accounts that have not signed in since the import.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function findHeldIds(array $userIds): array;

    /**
     * The accounts that are not anonymised, newest first; the search text matches part of the name or the email.
     *
     * @return Page<User>
     */
    public function search(?string $search, PageRequest $request): Page;

    /**
     * The accounts of this type that came from the import and have not signed in since (any status, never an anonymised one), with an id after `$afterId`, in id order. With
     * `$emails` only the accounts with these (lower case) addresses.
     *
     * @param  list<string>|null  $emails
     * @return list<User>
     */
    public function findImportedAwaitingSignIn(UserType $type, int $afterId, int $limit, ?array $emails = null): array;

    /** How many imported accounts of this type (never an anonymised one) have signed in since the import, or have not. */
    public function countImported(UserType $type, bool $signedIn): int;

    /** Inserts a new user or updates an existing one, and returns the stored aggregate (with its id). */
    public function save(User $user): User;
}
