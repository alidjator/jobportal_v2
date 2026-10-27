<?php

namespace Src\Company\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Interface\Http\MemberOption;

/**
 * A member the holder of `jobs.assign` may put on a job: the account id, the name and the company role (`company_admin` or `member`).
 *
 * @property MemberOption $resource
 */
final class MemberOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name, 'role' => $this->resource->role];
    }
}
