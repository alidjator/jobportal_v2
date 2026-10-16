<?php

namespace Src\Approval\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Approval\Domain\Delegation;

/** @property Delegation $resource */
final class DelegationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'delegate_user_id' => $this->resource->delegateUserId,
            'from' => $this->resource->from->format('Y-m-d'),
            'to' => $this->resource->to->format('Y-m-d'),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
