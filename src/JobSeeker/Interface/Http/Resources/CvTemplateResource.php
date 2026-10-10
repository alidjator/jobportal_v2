<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Domain\CvTemplateState;

/**
 * A CV template a job seeker may choose.
 *
 * @property CvTemplateState $resource
 */
final class CvTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['key' => $this->resource->template->value, 'name' => $this->resource->template->label(), 'is_default' => $this->resource->isDefault];
    }
}
