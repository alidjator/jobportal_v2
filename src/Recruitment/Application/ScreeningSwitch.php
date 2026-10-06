<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The per-company switch of the screening feature: while it is off, nothing about screening can be changed (reading stays possible). */
final readonly class ScreeningSwitch
{
    public function __construct(private Settings $settings) {}

    public function isOn(int $companyId): bool
    {
        return $this->settings->boolean(RecruitmentSettings::SCREENING_ENABLED, $companyId);
    }

    /**
     * @throws AccessDenied `screening-disabled`
     */
    public function assertOn(int $companyId): void
    {
        if (! $this->isOn($companyId)) {
            throw new AccessDenied('Screening questions are switched off for this company.', 'screening-disabled');
        }
    }
}
