<?php

namespace Src\Identity\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Application\Users\AdminUserView;

/** @property AdminUserView $resource */
final class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        return [
            'id' => $user->id,
            'type' => $user->type->value,
            'name' => $user->name(),
            'email' => $user->email(),
            'email_verified' => $user->isEmailVerified(),
            'status' => $user->status()->value,
            'locale' => $user->locale(),
            'first_login_at' => $user->firstLoginAt()?->format(DateTimeInterface::ATOM),
            'last_login_at' => $user->lastLoginAt()?->format(DateTimeInterface::ATOM),
            'deletion_requested_at' => $user->deletionRequestedAt()?->format(DateTimeInterface::ATOM),
            'email_suppressed' => $this->resource->emailSuppressionReason !== null,
            'email_suppression_reason' => $this->resource->emailSuppressionReason,
        ];
    }
}
