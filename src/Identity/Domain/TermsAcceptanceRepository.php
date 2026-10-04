<?php

namespace Src\Identity\Domain;

use DateTimeImmutable;

/** Proof that a user accepted a specific version of a legal document (`identity_terms_acceptances`). Rows are never changed, except that anonymisation clears the IP address. */
interface TermsAcceptanceRepository
{
    /**
     * Of the given documents, the types that the user has accepted at exactly the given version.
     *
     * @param  array<string, int>  $documents  document type mapped to version
     * @return list<string>
     */
    public function acceptedTypes(int $userId, array $documents): array;

    /** Records the acceptance. Recording the same version twice keeps the first record. */
    public function record(int $userId, string $type, int $version, string $locale, ?string $ipAddress, DateTimeImmutable $acceptedAt): void;
}
