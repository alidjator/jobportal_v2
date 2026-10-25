<?php

namespace Src\Company\Application\Profile;

use Src\Company\Application\Images\CompanyImageFile;
use Src\Company\Domain\CompanyLocation;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\LocationStatus;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The public page of a company. A company that is suspended or hidden by its admin looks the same as one that does not exist. */
final readonly class GetPublicCompany
{
    public function __construct(
        private CompanyRepository $companies,
        private CompanyLocationRepository $companyLocations,
        private ReferenceValues $references,
        private LocationReferences $locations,
        private TenantRegistry $tenants,
    ) {}

    /** @throws EntityNotFound `company-not-found` */
    public function execute(string $slug, string $language): PublicCompanyView
    {
        $company = $this->companies->findBySlug($slug);

        if ($company === null || ! $company->isPublic()) {
            throw new EntityNotFound('Company not found.', 'company-not-found');
        }

        $profile = $company->profile();

        return new PublicCompanyView(
            (int) $company->id,
            $profile->name,
            $company->slug(),
            $profile->bio,
            $profile->vision,
            $profile->website,
            $profile->establishedOn,
            $this->references->name('industries', $profile->industryId, $language),
            $this->references->name('organization-types', $profile->organizationTypeId, $language),
            $profile->teamSizeId === null ? null : $this->references->name('team-sizes', $profile->teamSizeId, $language),
            array_map(fn (CompanyLocation $location): PublicCompanyLocation => new PublicCompanyLocation(
                $location->name,
                $location->type,
                $location->isMain,
                $location->address->line,
                $this->locations->names($location->address->provinceId, $location->address->cityId, $location->address->subdistrictId, $location->address->villageId, $location->address->postalCodeId),
                $location->coordinates,
            ), $this->companyLocations->forCompany((int) $company->id, LocationStatus::Active)),
            $company->verifiedAt(),
            $company->logoPath() === null ? null : CompanyImageFile::version($company->logoPath()),
            $company->bannerPath() === null ? null : CompanyImageFile::version($company->bannerPath()),
            $company->id !== null && $this->tenants->legalEntityOf($company->id) !== null,
        );
    }
}
