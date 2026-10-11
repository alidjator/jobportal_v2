<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/**
 * A psychologist of a provider, tied to the account that signs in. The STR (registration certificate) and SILP (practice licence) numbers
 * and their validity dates decide whether the psychologist may sign reports: both must be valid through the day of signing.
 */
final class Psychologist
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly int $providerId,
        private string $strNumber,
        private DateTimeImmutable $strValidUntil,
        private string $silpNumber,
        private DateTimeImmutable $silpValidUntil,
        private PsychologistStatus $status,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function join(
        int $userId,
        int $providerId,
        string $strNumber,
        DateTimeImmutable $strValidUntil,
        string $silpNumber,
        DateTimeImmutable $silpValidUntil,
    ): self {
        return new self(null, $userId, $providerId, $strNumber, $strValidUntil, $silpNumber, $silpValidUntil, PsychologistStatus::Active);
    }

    public function strNumber(): string
    {
        return $this->strNumber;
    }

    public function strValidUntil(): DateTimeImmutable
    {
        return $this->strValidUntil;
    }

    public function silpNumber(): string
    {
        return $this->silpNumber;
    }

    public function silpValidUntil(): DateTimeImmutable
    {
        return $this->silpValidUntil;
    }

    public function status(): PsychologistStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /** True when the psychologist is active and both licences are still valid on the day of `$now`. */
    public function canSignReports(DateTimeImmutable $now): bool
    {
        $today = $now->format('Y-m-d');

        return $this->isActive()
            && $this->strValidUntil->format('Y-m-d') >= $today
            && $this->silpValidUntil->format('Y-m-d') >= $today;
    }

    public function updateCredentials(
        string $strNumber,
        DateTimeImmutable $strValidUntil,
        string $silpNumber,
        DateTimeImmutable $silpValidUntil,
    ): void {
        $this->strNumber = $strNumber;
        $this->strValidUntil = $strValidUntil;
        $this->silpNumber = $silpNumber;
        $this->silpValidUntil = $silpValidUntil;
    }

    public function suspend(): void
    {
        $this->status = PsychologistStatus::Suspended;
    }

    public function restore(): void
    {
        $this->status = PsychologistStatus::Active;
    }
}
