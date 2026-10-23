<?php

namespace Src\Recruitment\Domain;

/** The system meaning of a stage. The values match the check constraint of `recruitment_stages.type`; every company has one default stage per type. */
enum StageType: string
{
    case Applied = 'applied';
    case Screening = 'screening';
    case Assessment = 'assessment';
    case Interview = 'interview';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /** Rejected and withdrawn applications are final: they never move back to an active stage. */
    public function isTerminal(): bool
    {
        return $this === self::Rejected || $this === self::Withdrawn;
    }

    /** A recruiter may move an application into the active stages (not into the final ones: rejecting has its own action and only the applicant withdraws). */
    public function acceptsRecruiterMoves(): bool
    {
        return ! $this->isTerminal();
    }

    /** An applicant may withdraw from every active stage except `hired` (and the final ones, which are over). */
    public function allowsWithdrawal(): bool
    {
        return match ($this) {
            self::Applied, self::Screening, self::Assessment, self::Interview, self::Offer => true,
            self::Hired, self::Rejected, self::Withdrawn => false,
        };
    }

    /** Whether a company may add stages of this type besides the default one. */
    public function allowsExtraStages(): bool
    {
        return match ($this) {
            self::Screening, self::Assessment, self::Interview, self::Offer => true,
            self::Applied, self::Hired, self::Rejected, self::Withdrawn => false,
        };
    }

    /** The name a default stage starts with (the product is Indonesian); the company may rename it, and the name is data, never translated afterwards. */
    public function defaultName(): string
    {
        return match ($this) {
            self::Applied => 'Dilamar',
            self::Screening => 'Penyaringan',
            self::Assessment => 'Asesmen',
            self::Interview => 'Wawancara',
            self::Offer => 'Penawaran',
            self::Hired => 'Diterima',
            self::Rejected => 'Ditolak',
            self::Withdrawn => 'Mengundurkan diri',
        };
    }
}
