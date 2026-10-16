<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Organization\Application\Structure\UnitInput;

/**
 * A unit. A new one takes `legal_entity_id`, `code` (letters, digits, dot, dash, underscore; up to 30), `name`, `unit_type_id` and optionally `parent_unit_id` and
 * `cost_centre_id`; an edit takes any of `name`, `parent_unit_id`, `unit_type_id` and `cost_centre_id` (null clears the parent or the cost centre) as a new version. Both take
 * `valid_from` and `reason`.
 */
final class UnitRequest extends FormRequest
{
    use DatedChangeFields;

    private const array PROPERTY_BY_FIELD = ['name' => 'name', 'parent_unit_id' => 'parentUnitId', 'unit_type_id' => 'unitTypeId', 'cost_centre_id' => 'costCentreId'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $new = $this->isMethod('POST');
        $presence = $new ? 'required' : 'sometimes';

        return [
            'legal_entity_id' => [...($new ? ['required'] : ['prohibited']), 'integer', 'min:1'],
            'code' => [...($new ? ['required'] : ['prohibited']), 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => [$presence, 'string', 'min:2', 'max:150'],
            'unit_type_id' => [$presence, 'integer', 'min:1'],
            'parent_unit_id' => ['nullable', 'integer', 'min:1'],
            'cost_centre_id' => ['nullable', 'integer', 'min:1'],
            ...$this->datedChangeRules(),
        ];
    }

    public function legalEntityId(): int
    {
        return $this->integer('legal_entity_id');
    }

    public function code(): string
    {
        return trim($this->string('code')->toString());
    }

    public function unit(): UnitInput
    {
        return new UnitInput(
            trim($this->string('name')->toString()),
            $this->filled('parent_unit_id') ? $this->integer('parent_unit_id') : null,
            $this->integer('unit_type_id'),
            $this->filled('cost_centre_id') ? $this->integer('cost_centre_id') : null,
        );
    }

    /**
     * Only the fields the request holds, keyed by the property name of the version.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = [];

        foreach (self::PROPERTY_BY_FIELD as $field => $property) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $changes[$property] = $field === 'name' ? trim((string) $value) : ($value === null ? null : (int) $value);
            }
        }

        return $changes;
    }
}
