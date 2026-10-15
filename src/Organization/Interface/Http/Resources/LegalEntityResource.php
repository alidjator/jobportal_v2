<?php

namespace Src\Organization\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Organization\Application\Group\LegalEntityView;

/**
 * A legal entity with its tenant (`tenant` is null for an entity without recruitment).
 *
 * @property LegalEntityView $resource
 */
final class LegalEntityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entity = $this->resource->entity;

        return [
            'id' => $entity->id,
            'legal_name' => $entity->legalName,
            'legal_form' => $entity->legalForm->value,
            'npwp' => $entity->npwp,
            'nib' => $entity->nib,
            'address_line' => $entity->addressLine,
            'province_id' => $entity->provinceId,
            'city_id' => $entity->cityId,
            'subdistrict_id' => $entity->subdistrictId,
            'village_id' => $entity->villageId,
            'postal_code_id' => $entity->postalCodeId,
            'status' => $entity->status->value,
            'tenant' => $this->resource->tenantCompanyId === null ? null : [
                'company_id' => $this->resource->tenantCompanyId,
                'name' => $this->resource->tenantName,
                'status' => $this->resource->tenantStatus,
            ],
        ];
    }
}
