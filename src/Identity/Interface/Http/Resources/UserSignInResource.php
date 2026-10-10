<?php

namespace Src\Identity\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Application\Auth\UserSignInResult;

/**
 * The token a user receives when signing in or registering, with their account.
 *
 * @property UserSignInResult $resource
 */
final class UserSignInResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token,
            'token_type' => 'Bearer',
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
            'user' => new UserResource($this->resource->user),
        ];
    }
}
