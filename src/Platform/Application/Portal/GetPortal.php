<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Company\PublicCompanyCard;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Application\Portal\PortalBrandValues;
use Src\Shared\Application\Portal\PortalOwner;
use Src\Shared\Application\Portal\PortalOwnerType;

/**
 * The portal for a visitor. A company that is missing, suspended or hidden is left out and the site stays up. With a preview token the draft of the owner it names takes the
 * place of the live values it would replace: the identity for the platform, the brand for a group or a company.
 */
final readonly class GetPortal
{
    public function __construct(
        private Portals $portals,
        private PortalPreviewTokens $tokens,
        private PublicCompanies $companies,
        private CompanyDirectory $directory,
        private GroupDirectory $groups,
    ) {}

    public function execute(string $language, ?string $previewToken = null): PortalView
    {
        $previewing = $previewToken === null ? null : $this->tokens->read($previewToken);
        $identity = $this->portals->identity() ?? PortalIdentity::marketplace();
        $preview = false;

        if ($previewing?->type === PortalOwnerType::Platform && ($draft = $this->portals->draft($previewing)) !== null) {
            $identity = PortalIdentity::fromArray($draft->payload);
            $preview = true;
        }

        if ($identity->isMarketplace()) {
            return new PortalView(PortalIdentity::MARKETPLACE, null, null, null, null, 1, [], $preview);
        }

        $shown = $this->companies->cards($identity->companyIds);
        $cards = array_values(array_filter(array_map(fn (int $id) => $shown[$id] ?? null, $identity->companyIds)));
        $owner = $this->brandOwner($identity, $cards);
        $brand = $this->portals->brand($owner) ?? PortalBrandValues::default();

        if ($previewing !== null && $previewing->type === $owner->type && $previewing->id === $owner->id && ($draft = $this->portals->draft($owner)) !== null) {
            $brand = PortalBrandValues::fromArray($draft->payload);
            $preview = true;
        }

        return new PortalView(
            $identity->mode,
            $identity->scope,
            $brand->name ?? $this->defaultName($owner, $cards),
            $this->localized($language, $brand->taglineId, $brand->taglineEn),
            $this->localized($language, $brand->welcomeId, $brand->welcomeEn),
            $brand->preset,
            $cards,
            $preview,
        );
    }

    /** @param  list<PublicCompanyCard>  $cards */
    private function brandOwner(PortalIdentity $identity, array $cards): PortalOwner
    {
        if ($identity->scope === PortalIdentity::SCOPE_COMPANY) {
            return PortalOwner::company($identity->companyIds[0]);
        }

        // The group of the first company that can be shown; when none can, of the first named company that still has a group.
        foreach ([...array_map(fn ($card): int => $card->id, $cards), ...$identity->companyIds] as $companyId) {
            $groupId = $this->groups->groupOfCompany($companyId);

            if ($groupId !== null) {
                return PortalOwner::group($groupId);
            }
        }

        return PortalOwner::group(0);
    }

    /** @param  list<PublicCompanyCard>  $cards */
    private function defaultName(PortalOwner $owner, array $cards): ?string
    {
        if ($owner->type === PortalOwnerType::Group) {
            return $this->groups->nameOfGroup($owner->id);
        }

        return $cards[0]->name ?? $this->directory->summaries([$owner->id])[$owner->id]->name ?? null;
    }

    private function localized(string $language, ?string $indonesian, ?string $english): ?string
    {
        return $language === 'en' ? ($english ?? $indonesian) : ($indonesian ?? $english);
    }
}
