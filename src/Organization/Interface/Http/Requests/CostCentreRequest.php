<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A cost centre: a new one takes `legal_entity_id`, `code` (letters, digits, dot, dash, underscore; up to 20) and `name`; an edit takes `name`. */
final class CostCentreRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $new = $this->isMethod('POST');

        return [
            'legal_entity_id' => [...($new ? ['nullable'] : ['prohibited']), 'integer', 'min:1'],
            'code' => [...($new ? ['required'] : ['prohibited']), 'string', 'max:20', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
        ];
    }

    /** The legal entity the call is about; none on a `single` installation, which has one (`StructureEntity`). */
    public function legalEntityId(): ?int
    {
        return $this->filled('legal_entity_id') ? $this->integer('legal_entity_id') : null;
    }

    public function code(): string
    {
        return trim($this->string('code')->toString());
    }

    public function name(): string
    {
        return trim($this->string('name')->toString());
    }
}
