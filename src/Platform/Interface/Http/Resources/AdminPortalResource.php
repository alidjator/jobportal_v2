<?php

namespace Src\Platform\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Application\Portal\PortalAdminPage;
use Src\Platform\Application\Portal\PortalIdentity;
use Src\Platform\Application\Portal\PortalSkippedCompany;

/**
 * The portal screen of the platform admin: the live identity, the draft waiting (null when none) and the companies the live identity names but the career site skips.
 *
 * @property PortalAdminPage $resource
 */
final class AdminPortalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'live' => $this->identity($this->resource->live),
            'live_is_default' => $this->resource->liveIsDefault,
            'draft' => $this->resource->draft === null ? null : $this->identity($this->resource->draft),
            'draft_saved_at' => $this->resource->draftSavedAt?->format(DateTimeInterface::ATOM),
            'draft_differs' => $this->resource->draftDiffers(),
            'skipped' => array_map(fn (PortalSkippedCompany $company): array => [
                'company_id' => $company->id,
                'name' => $company->name,
                /** `missing`, `suspended` or `hidden` */
                'reason' => $company->reason,
            ], $this->resource->skipped),
        ];
    }

    /** @return array{mode: string, scope: string|null, company_ids: list<int>} */
    private function identity(PortalIdentity $identity): array
    {
        return $identity->toArray();
    }
}
