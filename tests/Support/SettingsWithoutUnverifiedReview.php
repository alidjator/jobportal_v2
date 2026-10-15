<?php

namespace Tests\Support;

use Closure;
use Src\Job\Application\JobSettings;
use Src\Shared\Application\Settings\Settings;

/**
 * Feature tests build companies without the verification badge, and the real default sends the jobs of such a company to review. This wrapper answers
 * "off" for that one setting unless the test turns it on (`$unverifiedReview` of `FeatureTestCase`), so the tests of the job flow need not verify a company.
 */
final readonly class SettingsWithoutUnverifiedReview implements Settings
{
    /** @param  Closure(): bool  $enabled */
    public function __construct(private Settings $inner, private Closure $enabled) {}

    public function integer(string $key, ?int $companyId = null): int
    {
        return $this->inner->integer($key, $companyId);
    }

    public function boolean(string $key, ?int $companyId = null): bool
    {
        if ($key === JobSettings::APPROVAL_FOR_UNVERIFIED && ! ($this->enabled)()) {
            return false;
        }

        return $this->inner->boolean($key, $companyId);
    }

    public function text(string $key, ?int $companyId = null): string
    {
        return $this->inner->text($key, $companyId);
    }

    public function choices(string $key, ?int $companyId = null): array
    {
        return $this->inner->choices($key, $companyId);
    }
}
