<?php

namespace Src\Campaign\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Campaign\Application\CampaignInput;
use Src\Campaign\Domain\CampaignType;

/**
 * A campaign to create (`POST`: also `code`, and `company_id` for a platform admin, absent for a platform campaign) or to change (`PUT`: every editable field is sent;
 * the code and the company cannot change). Dates are Jakarta calendar days; a missing date leaves that end of the period open. `is_active` is true when left out on create.
 */
final class SaveCampaignRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'code' => $creating ? ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/'] : ['prohibited'],
            'company_id' => $creating ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', Rule::enum(CampaignType::class)],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'is_active' => [$creating ? 'nullable' : 'required', 'boolean'],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }

    public function companyId(): ?int
    {
        return $this->filled('company_id') ? $this->integer('company_id') : null;
    }

    public function fields(): CampaignInput
    {
        return new CampaignInput(
            trim($this->string('name')->toString()),
            CampaignType::from($this->string('type')->toString()),
            $this->filled('starts_on') ? $this->string('starts_on')->toString() : null,
            $this->filled('ends_on') ? $this->string('ends_on')->toString() : null,
            $this->boolean('is_active', true),
        );
    }
}
