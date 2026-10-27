<?php

namespace Src\Assessment\Domain;

/** A submission of the admin's queue with who submitted it: the account of the psychologist and their institution. */
final readonly class LicenceQueueRow
{
    public function __construct(
        public LicenceSubmission $submission,
        public int $psychologistUserId,
        public int $providerId,
        public string $providerName,
    ) {}
}
