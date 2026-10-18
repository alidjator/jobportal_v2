<?php

namespace Src\Recruitment\Domain;

/** The kind of employment an offer is for: permanent (PKWTT), contract (PKWT), internship, daily (harian lepas) or freelance. */
enum EmploymentType: string
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Internship = 'internship';
    case Daily = 'daily';
    case Freelance = 'freelance';

    /** Whether the offer states a length in months (a contract and an internship end; the others do not). */
    public function hasContractLength(): bool
    {
        return $this === self::Contract || $this === self::Internship;
    }
}
