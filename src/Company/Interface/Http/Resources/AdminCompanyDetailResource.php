<?php

namespace Src\Company\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Company\Application\Admin\AdminCompanyDetail;

/**
 * A company for admins: the whole profile as the company stores it, the counts, who created it, and (while suspended) when and why. The picture
 * URLs of the company's own profile are not repeated here (an admin cannot call the company's own routes); `has_logo` and `has_banner` say whether
 * there are pictures.
 *
 * @property AdminCompanyDetail $resource
 */
final class AdminCompanyDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $company = $this->resource->company;
        $profile = (new CompanyProfileResource($company))->toArray($request);
        unset($profile['logo_url'], $profile['banner_url']);

        return [
            ...$profile,
            'has_logo' => $company->logoPath() !== null,
            'has_banner' => $company->bannerPath() !== null,
            'members_count' => $this->resource->counts->members,
            'pending_invitations_count' => $this->resource->counts->pendingInvitations,
            'created_by_admin_id' => $company->createdByAdminId(),
            'created_at' => $company->createdAt()?->format(DateTimeInterface::ATOM),
            'suspended_at' => $company->suspendedAt()?->format(DateTimeInterface::ATOM),
            'suspension_reason' => $company->suspensionReason(),
        ];
    }
}
