<?php

namespace Src\Identity\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Domain\User;

/** @property User $resource */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'name' => $this->resource->name(),
            'email' => $this->resource->email(),
            'email_verified' => $this->resource->isEmailVerified(),
            'status' => $this->resource->status()->value,
            'locale' => $this->resource->locale(),
        ];
    }
}
