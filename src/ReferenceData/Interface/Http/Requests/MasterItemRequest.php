<?php

namespace Src\ReferenceData\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\ReferenceData\Application\Masters\FieldType;
use Src\ReferenceData\Application\Masters\MasterCatalog;
use Src\ReferenceData\Application\Masters\MasterDefinition;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Create or update an item of an admin-managed master. The extra columns and their rules come from the master's definition. */
final class MasterItemRequest extends FormRequest
{
    public function definition(): MasterDefinition
    {
        $key = $this->route('master');
        $definition = is_string($key) ? MasterCatalog::find($key) : null;

        return $definition !== null && $definition->adminManaged ? $definition : throw EntityNotFound::of('Reference list', is_string($key) ? $key : null);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'array'],
            'name.id' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'is_active' => ['nullable', 'boolean'],
        ];

        // Without a `master` route parameter (the API documentation reads the rules this way) only the generic rules apply.
        if ($this->route('master') === null) {
            return $rules;
        }

        foreach ($this->definition()->extras as $field) {
            $presence = $field->required ? 'required' : 'nullable';

            $rules[$field->name] = match ($field->type) {
                FieldType::Text => [$presence, 'string', 'max:'.$field->max],
                FieldType::Integer => [$presence, 'integer', 'min:0', 'max:'.$field->max],
                FieldType::Choice => [$presence, 'string', Rule::in($field->choices)],
            };
        }

        return $rules;
    }

    /** @return array<array-key, mixed> */
    public function name(): array
    {
        return $this->array('name');
    }

    public function sortOrder(): int
    {
        return $this->integer('sort_order');
    }

    public function isActive(): bool
    {
        return $this->boolean('is_active', true);
    }

    /** @return array<string, int|string|null> */
    public function extras(): array
    {
        $extras = [];

        foreach ($this->definition()->extras as $field) {
            $value = $this->input($field->name);

            $extras[$field->name] = match (true) {
                $value === null || $value === '' => null,
                $field->type === FieldType::Integer => (int) $value,
                default => (string) $value,
            };
        }

        return $extras;
    }
}
