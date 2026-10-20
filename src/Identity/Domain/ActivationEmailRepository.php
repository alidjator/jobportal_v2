<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;

/** The log of activation emails (`identity_activation_emails`). */
interface ActivationEmailRepository
{
    /** Takes the place for one email; false when the account already has one of an automatic kind (`activation`, `reminder`), so nothing is sent twice. */
    public function reserve(int $userId, ActivationKind $kind, ActivationVariant $variant, string $batch, DateTimeImmutable $at): bool;

    /** Gives the place back when the email could not be handed to the mailer. */
    public function release(int $userId, ActivationKind $kind): void;

    /**
     * When each of these accounts got an email of this kind (only the ones that did).
     *
     * @param  list<int>  $userIds
     * @return array<int, DateTimeImmutable>
     */
    public function sentAt(ActivationKind $kind, array $userIds): array;

    /**
     * The batch of the latest automatic email (activation or reminder) sent to accounts of these variants, with the accounts it went to; null before the first one.
     *
     * @param  list<ActivationVariant>  $variants
     * @return array{batch: string, userIds: list<int>}|null
     */
    public function latestBatch(array $variants): ?array;
}
