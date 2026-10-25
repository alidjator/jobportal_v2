<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Application\Portal\PortalBrandEditor;
use Src\Shared\Application\Portal\PortalBrandPage;
use Src\Shared\Application\Portal\PortalBrandValues;
use Src\Shared\Application\Portal\PortalOwner;
use Src\Shared\Application\Portal\PortalOwnerType;
use Src\Shared\Application\Portal\PortalPreview;

/** Platform's side of the brand screen of a company or group: the live brand, its draft, and the four actions on the draft. */
final readonly class EditPortalBrand implements PortalBrandEditor
{
    public function __construct(
        private Portals $portals,
        private SavePortalDraft $save,
        private DiscardPortalDraft $discard,
        private PublishPortalBrand $publish,
        private CreatePortalPreview $preview,
        private CompanyDirectory $companies,
        private GroupDirectory $groups,
    ) {}

    public function page(PortalOwner $owner): PortalBrandPage
    {
        $live = $this->portals->brand($owner);
        $draft = $this->portals->draft($owner);

        return new PortalBrandPage(
            $live ?? PortalBrandValues::default(),
            $live === null,
            $draft === null ? null : PortalBrandValues::fromArray($draft->payload),
            $draft?->savedAt,
            $this->defaultName($owner),
        );
    }

    public function saveDraft(PortalOwner $owner, int $userId, PortalBrandValues $values): PortalBrandPage
    {
        $this->save->execute($owner, $userId, $values);

        return $this->page($owner);
    }

    public function discardDraft(PortalOwner $owner): PortalBrandPage
    {
        $this->discard->execute($owner);

        return $this->page($owner);
    }

    public function publish(PortalOwner $owner, int $userId, ?string $ipAddress, ?string $userAgent): PortalBrandPage
    {
        $this->publish->execute($owner, $userId, $ipAddress, $userAgent);

        return $this->page($owner);
    }

    public function preview(PortalOwner $owner): PortalPreview
    {
        return $this->preview->execute($owner);
    }

    private function defaultName(PortalOwner $owner): string
    {
        return match ($owner->type) {
            PortalOwnerType::Group => $this->groups->nameOfGroup($owner->id),
            default => $this->companies->summaries([$owner->id])[$owner->id]->name ?? null,
        } ?? '';
    }
}
