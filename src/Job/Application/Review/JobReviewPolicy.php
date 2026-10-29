<?php

namespace Src\Job\Application\Review;

use Src\Job\Application\JobSettings;
use Src\Job\Domain\JobContent;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Portal\VerificationFeature;
use Src\Shared\Application\Settings\Settings;

/**
 * Whether a job needs an admin's review before it is live (when it is published or reopened) or goes back to review (when a live job's core content
 * changes): the company's settings require approval, or the company is not verified and `jobs.approval_for_unverified` is on, or the content matches a
 * suspicious phrase or carries a contact detail. A company linked to a legal entity counts as verified and is exempt from the last two
 * (`erp-governance.md` 1.2; the link arrives with `erp-organization.md`, until then every company is external).
 */
final readonly class JobReviewPolicy
{
    public function __construct(
        private Settings $settings,
        private CompanyDirectory $companies,
        private VerificationFeature $verification,
        private SuspiciousContentScanner $scanner,
    ) {}

    public function required(int $companyId, JobContent $content): bool
    {
        if ($this->settings->boolean(JobSettings::REQUIRE_APPROVAL, $companyId)) {
            return true;
        }

        if ($this->verification->enabled() && $this->settings->boolean(JobSettings::APPROVAL_FOR_UNVERIFIED) && ! $this->companies->isVerified($companyId)) {
            return true;
        }

        return $this->scanner->flags($content) !== [];
    }
}
