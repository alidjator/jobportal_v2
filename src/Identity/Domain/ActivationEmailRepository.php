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

    /**
     * The activation emails of these variants for the report: when the first one went out (null before it), how many accounts got one, how many got the reminder, and who got one.
     *
     * @param  list<ActivationVariant>  $variants
     * @return array{firstSentAt: DateTimeImmutable|null, sent: int, reminders: int, userIds: list<int>}
     */
    public function summary(array $variants): array;

    /**
     * Every batch of automatic emails (activation and reminder) sent to these variants, in the order it started, with the accounts it went to.
     *
     * @param  list<ActivationVariant>  $variants
     * @return list<array{batch: string, sentAt: DateTimeImmutable, userIds: list<int>}>
     */
    public function batches(array $variants): array;

    /**
     * The emails of every kind this account got, oldest first.
     *
     * @return list<array{kind: string, batch: string, sentAt: DateTimeImmutable}>
     */
    public function ofUser(int $userId): array;
}
