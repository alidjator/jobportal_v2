<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The legal entity and the ids of the units to put in order, in the wanted order. Whether they belong to the entity is checked by the use case. */
final class ReorderUnitsRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'legal_entity_id' => ['nullable', 'integer', 'min:1'],
            'unit_ids' => ['required', 'array', 'min:1', 'max:500'],
            'unit_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /** The legal entity the call is about; none on a `single` installation, which has one (`StructureEntity`). */
    public function legalEntityId(): ?int
    {
        return $this->filled('legal_entity_id') ? $this->integer('legal_entity_id') : null;
    }

    /** @return list<int> */
    public function unitIds(): array
    {
        return array_values(array_map('intval', $this->array('unit_ids')));
    }
}
