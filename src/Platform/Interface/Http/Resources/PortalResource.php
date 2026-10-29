<?php

namespace Src\Platform\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Application\Portal\PortalView;
use Src\Shared\Application\Company\PublicCompanyCard;

/**
 * The career portal: the mode (`marketplace` or `company`), the scope of a company site, its brand in the language of the request and the companies it shows. In the marketplace
 * `companies` is empty and means every company.
 *
 * @property PortalView $resource
 */
final class PortalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'mode' => $this->resource->mode,
            'scope' => $this->resource->scope,
            /** `single` (one company) or `multi` (several companies) */
            'installation_type' => $this->resource->installationType(),
            /** `group` or `independent` for `multi`; null for `single` */
            'multi_kind' => $this->resource->multiKind(),
            'name' => $this->resource->name,
            'tagline' => $this->resource->tagline,
            'welcome' => $this->resource->welcome,
            'preset' => $this->resource->preset,
            /** `light`, `dark` or `system` (the visitor's own setting decides) */
            'theme_mode' => $this->resource->themeMode,
            'contrast' => $this->resource->contrast,
            /** `container` or `full` */
            'width' => $this->resource->width,
            'header_color' => $this->resource->headerColor,
            'preview' => $this->resource->preview,
            'companies' => array_map(fn (PublicCompanyCard $card): array => [
                'id' => $card->id,
                'name' => $card->name,
                'slug' => $card->slug,
                'verified' => $card->verified,
                'logo_url' => $card->logoUrl,
            ], $this->resource->companies),
        ];
    }
}
