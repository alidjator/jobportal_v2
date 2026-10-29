<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Portal\VerificationFeature;

/**
 * Whether the team of a company may see the contact data of an applicant (email, phone, WhatsApp) and download the resume, which usually carries it. While the
 * company is not verified they stay closed as long as the application is in `applied`; from `screening` on, or once the company is verified, everything is open. A company portal has no verification, so there they are always open.
 */
final readonly class ContactVisibility
{
    public function __construct(private CompanyDirectory $companies, private VerificationFeature $verification) {}

    public function isClosed(int $companyId, StageType $stage): bool
    {
        return $stage === StageType::Applied && $this->verification->enabled() && ! $this->companies->isVerified($companyId);
    }

    /** For a whole export of one company: whether the company is verified, so only the stage of each row is left to ask. */
    public function companyIsOpen(int $companyId): bool
    {
        return ! $this->verification->enabled() || $this->companies->isVerified($companyId);
    }

    public function closedFor(bool $companyIsOpen, StageType $stage): bool
    {
        return ! $companyIsOpen && $stage === StageType::Applied;
    }
}
