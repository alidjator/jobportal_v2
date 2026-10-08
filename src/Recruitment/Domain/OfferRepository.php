<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface OfferRepository
{
    public function findById(int $offerId): ?Offer;

    /** The pending offer of the application, if any (one past its deadline counts until the hourly command marks it expired). */
    public function active(int $applicationId, bool $forUpdate = false): ?Offer;

    /** @return list<Offer> newest first */
    public function forApplication(int $applicationId): array;

    /** Saves a new offer; fails when the application already has a pending one. */
    public function add(Offer $offer): Offer;

    /** Saves the state of an offer that exists. */
    public function update(Offer $offer): void;

    /** Withdraws the pending offer of the application and returns it, or null when it had none. */
    public function withdrawActive(int $applicationId): ?Offer;

    /**
     * Pending offers whose deadline has passed.
     *
     * @return list<Offer>
     */
    public function dueForExpiry(DateTimeImmutable $now): array;

    /**
     * Pending offers whose deadline falls after `$from` and no later than `$until` and that were not reminded yet.
     *
     * @return list<Offer>
     */
    public function dueForReminder(DateTimeImmutable $from, DateTimeImmutable $until): array;
}
