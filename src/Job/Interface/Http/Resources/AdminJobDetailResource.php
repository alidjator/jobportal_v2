<?php

namespace Src\Job\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Admin\AdminJobDetail;
use Src\Job\Application\Review\JobFlag;

/**
 * A job as an admin sees it: everything the company sees, the company it belongs to, and the decision details (reason, when and by which admin) whenever they
 * exist, in every status.
 *
 * @property AdminJobDetail $resource
 */
final class AdminJobDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $job = $this->resource->detail->job;
        $company = $this->resource->company;

        $data = (new JobDetailResource($this->resource->detail))->toArray($request);

        if ($job->statusReason !== null) {
            $data['status_reason'] = $job->statusReason;
        }

        return [
            ...$data,
            'company' => $company === null
                ? ['id' => $job->companyId, 'name' => null, 'slug' => null, 'status' => null]
                : ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug, 'status' => $company->status],
            'reviewed_by_admin_id' => $job->reviewedByAdminId,
            'company_verified' => $this->resource->companyVerified,
            'flags' => array_map(static fn (JobFlag $flag): array => ['kind' => $flag->kind, 'value' => $flag->value, 'field' => $flag->field], $this->resource->flags),
        ];
    }
}
