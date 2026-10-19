<?php

namespace Src\Shared\Application\Company;

/** The contexts that point to work locations. Each context adds its own from its service provider; Company asks all of them before a location is deleted. */
final class CompanyLocationUsages
{
    /** @var list<CompanyLocationUsage> */
    private array $usages = [];

    public function add(CompanyLocationUsage ...$usages): void
    {
        foreach ($usages as $usage) {
            $this->usages[] = $usage;
        }
    }

    public function isHeld(int $companyId, int $locationId): bool
    {
        foreach ($this->usages as $usage) {
            if ($usage->holds($companyId, $locationId)) {
                return true;
            }
        }

        return false;
    }
}
