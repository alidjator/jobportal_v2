<?php

namespace Src\Workforce\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Workforce\Domain\RequisitionStatus;
use Src\Workforce\Domain\RequisitionType;

/** Narrows the requisition list: `?legal_entity_id=` (group side), `?status=`, `?type=` and `?position_id=`. */
final class ListRequisitionsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'legal_entity_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::enum(RequisitionStatus::class)],
            'type' => ['sometimes', 'string', Rule::enum(RequisitionType::class)],
            'position_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function legalEntityId(): ?int
    {
        return $this->has('legal_entity_id') ? $this->integer('legal_entity_id') : null;
    }

    public function status(): ?RequisitionStatus
    {
        return $this->has('status') ? RequisitionStatus::from($this->string('status')->toString()) : null;
    }

    public function type(): ?RequisitionType
    {
        return $this->has('type') ? RequisitionType::from($this->string('type')->toString()) : null;
    }

    public function positionId(): ?int
    {
        return $this->has('position_id') ? $this->integer('position_id') : null;
    }
}
