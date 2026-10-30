<?php

namespace Src\Approval\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Interface\Http\MemberChoice;

/**
 * A colleague of the own company in an approval: the account id and the name, nothing else.
 *
 * @property MemberChoice $resource
 */
final class ApprovalPersonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name];
    }
}
