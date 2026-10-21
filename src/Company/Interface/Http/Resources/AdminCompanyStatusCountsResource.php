<?php

namespace Src\Company\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Admin\AdminCompanyStatusCounts;

/**
 * The company counts per status: `all` and one key per status.
 *
 * @property AdminCompanyStatusCounts $resource
 */
final class AdminCompanyStatusCountsResource extends JsonResource
{
    /** @return array<string, int> */
    public function toArray(Request $request): array
    {
        return ['all' => $this->resource->all, ...$this->resource->byStatus];
    }
}
