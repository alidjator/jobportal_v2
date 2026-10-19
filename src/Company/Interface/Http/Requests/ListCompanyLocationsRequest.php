<?php

namespace Src\Company\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Company\Domain\LocationStatus;

/** The list of work locations, optionally only the active or only the inactive ones (`filter[status]`). */
final class ListCompanyLocationsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['filter.status' => ['sometimes', 'string', Rule::enum(LocationStatus::class)]];
    }

    public function status(): ?LocationStatus
    {
        $status = $this->validated('filter.status');

        return is_string($status) ? LocationStatus::from($status) : null;
    }
}
