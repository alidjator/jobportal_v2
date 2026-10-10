<?php

namespace Src\Newsletter\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface SubscriberRepository
{
    /** @param  string  $email  lower case */
    public function findByEmail(string $email, bool $forUpdate = false): ?Subscriber;

    public function find(int $id, bool $forUpdate = false): ?Subscriber;

    public function findByConfirmTokenHash(string $hash, bool $forUpdate = false): ?Subscriber;

    /** Stores a new subscriber and returns it with its id. When the address was added at the same moment by another request, that row is returned instead. */
    public function add(Subscriber $subscriber): Subscriber;

    public function update(Subscriber $subscriber): void;

    public function delete(int $id): void;

    /** @return Page<Subscriber> */
    public function page(?string $search, ?SubscriberStatus $status, PageRequest $request): Page;

    /** @return array<string, int> the number of subscribers per status (every status present) */
    public function countsByStatus(): array;

    public function countConfirmed(): int;

    /**
     * The confirmed subscribers with an id above `$afterId`, oldest first.
     *
     * @return list<Subscriber>
     */
    public function confirmedAfter(int $afterId, int $limit): array;

    /** Deletes the pending subscribers whose confirmation link expired before `$before`. @return int how many */
    public function deleteExpiredPending(DateTimeImmutable $before): int;
}
