<?php

namespace Src\Assessment\Domain;

interface ProviderNoticeRepository
{
    /**
     * The psychologists to tell about waiting reviews: active ones of active providers, each with the count of their provider. A report waits when it is a draft nobody holds (a claim lapses after
     * 24 hours without a write) or when it was issued automatically and no psychologist reviewed it yet.
     *
     * @return list<ReviewsWaiting>
     */
    public function reviewsWaiting(): array;

    /** Remembers how many reviews waited when the psychologist was last told (or when the count fell, so a later rise is told again). */
    public function markReviewsNotified(int $psychologistId, int $count): void;

    /** @return list<PsychologistLicences> */
    public function psychologistsWithLicences(): array;

    /** @param  array<string, mixed>  $notified  see `PsychologistLicences` */
    public function markLicenceNotified(int $psychologistId, array $notified): void;

    /**
     * The batteries with automatic issue that the psychologist answers for.
     *
     * @return list<array{id: int, title: string}>
     */
    public function autoIssueBatteriesOf(int $psychologistId): array;

    /**
     * The accounts of the active psychologists of the provider.
     *
     * @return list<int>
     */
    public function activeUserIdsOfProvider(int $providerId): array;
}
