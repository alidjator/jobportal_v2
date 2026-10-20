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
            'imported_at' => $user->importedAt()?->format(DateTimeInterface::ATOM),
            'email_suppressed' => $this->resource->emailSuppressionReason !== null,
            'email_suppression_reason' => $this->resource->emailSuppressionReason,
            /** @var list<array{kind: string, batch: string, sent_at: string}> the activation emails the account got; only when one account is opened */
            'activation' => $this->when($this->resource->activationEmails !== null, fn (): array => array_map(static fn (array $email): array => ['kind' => $email['kind'], 'batch' => $email['batch'], 'sent_at' => $email['sentAt']->format(DateTimeInterface::ATOM)], $this->resource->activationEmails ?? [])),
            'only_company_admin' => $this->when($this->resource->onlyCompanyAdmin !== null, $this->resource->onlyCompanyAdmin),
        ];
    }
}
