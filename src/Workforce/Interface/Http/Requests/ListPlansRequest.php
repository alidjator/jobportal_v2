<?php

namespace Src\Workforce\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Workforce\Domain\PlanStatus;

/** Narrows the plan list: `?legal_entity_id=` (group side), `?fiscal_year=` and `?status=`. */
final class ListPlansRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'legal_entity_id' => ['sometimes', 'integer', 'min:1'],
            'fiscal_year' => ['sometimes', 'integer', 'between:2000,2100'],
            'status' => ['sometimes', 'string', Rule::enum(PlanStatus::class)],
        ];
    }

    public function legalEntityId(): ?int
    {
        return $this->has('legal_entity_id') ? $this->integer('legal_entity_id') : null;
    }

    public function fiscalYear(): ?int
    {
        return $this->has('fiscal_year') ? $this->integer('fiscal_year') : null;
    }

    public function status(): ?PlanStatus
    {
        return $this->has('status') ? PlanStatus::from($this->string('status')->toString()) : null;
    }
}
