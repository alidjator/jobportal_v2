<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\CreatedCompany;

/**
 * The new company and the invitation sent to its first company admin. The invitation token is never returned: it only travels in the email.
 *
 * @property CreatedCompany $resource
 */
final class CreatedCompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $company = $this->resource->company;
        $invitation = $this->resource->invitation;

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name(),
                'slug' => $company->slug(),
                'status' => $company->status()->value,
                'industry_id' => $company->industryId(),
                'organization_type_id' => $company->organizationTypeId(),
            ],
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'sent_at' => $invitation->sentAt->format(DateTimeInterface::ATOM),
                'expires_at' => $invitation->expiresAt->format(DateTimeInterface::ATOM),
            ],
        ];
    }
}
