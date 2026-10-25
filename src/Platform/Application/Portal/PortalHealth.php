<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\PublicCompanies;

/** Which of the companies an identity names the career site has to skip, and why. Marketplace names none. */
final readonly class PortalHealth
{
    public function __construct(private PublicCompanies $companies, private CompanyDirectory $directory) {}

    /** @return list<PortalSkippedCompany> in the order the identity names them */
    public function skipped(PortalIdentity $identity): array
    {
        if ($identity->isMarketplace() || $identity->companyIds === []) {
            return [];
        }

        $shown = $this->companies->cards($identity->companyIds);
        $left = array_values(array_filter($identity->companyIds, fn (int $id): bool => ! isset($shown[$id])));
        $summaries = $this->directory->summaries($left);

        return array_map(function (int $id) use ($summaries): PortalSkippedCompany {
            $summary = $summaries[$id] ?? null;

            return new PortalSkippedCompany(
                $id,
                $summary?->name,
                match (true) {
                    $summary === null => PortalSkippedCompany::MISSING,
                    $summary->status === 'suspended' => PortalSkippedCompany::SUSPENDED,
                    default => PortalSkippedCompany::HIDDEN,
                },
            );
        }, $left);
    }

    /** A short fingerprint of the skipped set, so a warning is sent once per change; null when nothing is skipped. @param  list<PortalSkippedCompany>  $skipped */
    public static function signature(array $skipped): ?string
    {
        if ($skipped === []) {
            return null;
        }

        $parts = array_map(fn (PortalSkippedCompany $company): string => "{$company->id}:{$company->reason}", $skipped);
        sort($parts);

        return sha1(implode(',', $parts));
    }
}
