<?php

namespace Src\Shared\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Portal\PortalBrandPage;
use Src\Shared\Application\Portal\PortalBrandValues;

/**
 * The brand screen of a company or group: the live brand, the draft waiting (null when none) and whether the draft differs from what is live.
 *
 * @property PortalBrandPage $resource
 */
final class PortalBrandPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'default_name' => $this->resource->defaultName,
            'live' => $this->brand($this->resource->live),
            'live_is_default' => $this->resource->liveIsDefault,
            'draft' => $this->resource->draft === null ? null : $this->brand($this->resource->draft),
            'draft_saved_at' => $this->resource->draftSavedAt?->format(DateTimeInterface::ATOM),
            'draft_differs' => $this->resource->draftDiffers(),
        ];
    }

    /** @return array{name: string|null, tagline_id: string|null, tagline_en: string|null, welcome_id: string|null, welcome_en: string|null, preset: int} */
    private function brand(PortalBrandValues $brand): array
    {
        return $brand->toArray();
    }
}
