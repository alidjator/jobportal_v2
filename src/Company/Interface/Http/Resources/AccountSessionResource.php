<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Application\Accounts\AccountSession;

/** The sign-in answer after accepting an invitation, shaped like the Identity sign-in answer. @property AccountSession $resource */
final class AccountSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token,
            'token_type' => 'Bearer',
            'expires_at' => $this->resource->expiresAt->format(DateTimeInterface::ATOM),
            'user' => [
                'id' => $this->resource->userId,
                'type' => $this->resource->type,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'email_verified' => $this->resource->emailVerified,
                'status' => $this->resource->status,
                'locale' => $this->resource->locale,
            ],
        ];
    }
}
