<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Domain\CvTemplateState;

/**
 * A CV template with its state, for an admin.
 *
 * @property CvTemplateState $resource
 */
final class AdminCvTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->template->value,
            'name' => $this->resource->template->label(),
            'enabled' => $this->resource->enabled,
            'is_default' => $this->resource->isDefault,
        ];
    }
}
