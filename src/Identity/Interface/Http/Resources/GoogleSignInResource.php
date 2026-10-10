<?php

namespace Src\Identity\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Application\Auth\GoogleSignInResult;

/**
 * The sign-in token plus whether this sign-in created the account (so the client can start onboarding).
 *
 * @property GoogleSignInResult $resource
 */
final class GoogleSignInResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new UserSignInResource($this->resource->signIn))->toArray($request),
            'is_new_user' => $this->resource->isNewUser,
        ];
    }
}
