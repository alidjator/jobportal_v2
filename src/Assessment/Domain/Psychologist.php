<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/**
 * A psychologist of a provider, tied to the account that signs in. The STR (registration certificate) and SILP (practice licence) numbers
 * and their validity dates decide whether the psychologist may sign reports: both must be valid through the day of signing. A psychologist
 * invited by a lead of the institution has no licence until they submit one, and cannot sign meanwhile.
 */
final class Psychologist
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly int $providerId,
        private ?string $strNumber,
        private ?DateTimeImmutable $strValidUntil,
        private ?string $silpNumber,
        private ?DateTimeImmutable $silpValidUntil,
        private PsychologistStatus $status,
        private PsychologistRole $role = PsychologistRole::Member,
        public readonly ?DateTimeImmutable $createdAt = null,
        private ?string $signaturePath = null,
    ) {}

    public static function join(
        int $userId,
        int $providerId,
        ?string $strNumber,
        ?DateTimeImmutable $strValidUntil,
        ?string $silpNumber,
        ?DateTimeImmutable $silpValidUntil,
        PsychologistRole $role = PsychologistRole::Member,
    ): self {
        return new self(null, $userId, $providerId, $strNumber, $strValidUntil, $silpNumber, $silpValidUntil, PsychologistStatus::Active, $role);
    }

    public function strNumber(): ?string
    {
        return $this->strNumber;
    }

    public function strValidUntil(): ?DateTimeImmutable
    {
        return $this->strValidUntil;
    }

    public function silpNumber(): ?string
    {
        return $this->silpNumber;
    }

    public function silpValidUntil(): ?DateTimeImmutable
    {
        return $this->silpValidUntil;
    }

    public function status(): PsychologistStatus
    {
        return $this->status;
    }

    public function role(): PsychologistRole
    {
        return $this->role;
    }

    /** The path of the signature image on the private disk, or null when the psychologist has not uploaded one. */
    public function signaturePath(): ?string
    {
        return $this->signaturePath;
    }

    public function changeSignature(?string $path): void
    {
        $this->signaturePath = $path;
    }

    public function isLead(): bool
    {
        return $this->role->isLead();
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
            && $this->strValidUntil !== null && $this->strValidUntil->format('Y-m-d') >= $today
            && $this->silpValidUntil !== null && $this->silpValidUntil->format('Y-m-d') >= $today;
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

    /** A licence renewal the platform admin approved: the number and the date of one licence. */
    public function renewLicence(LicenceKind $kind, string $number, DateTimeImmutable $validUntil): void
    {
        if ($kind === LicenceKind::Str) {
            $this->strNumber = $number;
            $this->strValidUntil = $validUntil;

            return;
        }

        $this->silpNumber = $number;
        $this->silpValidUntil = $validUntil;
    }

    public function changeRole(PsychologistRole $role): void
    {
        $this->role = $role;
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
