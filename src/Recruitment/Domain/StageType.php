<?php

namespace Src\Recruitment\Domain;

/** The system meaning of a stage. The values match the check constraint of `recruitment_stages.type`; every company has one default stage per type. */
enum StageType: string
{
    case Applied = 'applied';
    case Screening = 'screening';
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

    /** Whether a company may add stages of this type besides the default one. */
    public function allowsExtraStages(): bool
    {
        return match ($this) {
            self::Screening, self::Interview, self::Offer => true,
            self::Applied, self::Hired, self::Rejected, self::Withdrawn => false,
        };
    }

    /** The name a default stage starts with; the company may rename it. */
    public function defaultName(): string
    {
        return ucfirst($this->value);
    }
}
