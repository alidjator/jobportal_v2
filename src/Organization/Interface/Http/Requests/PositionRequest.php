<?php

namespace Src\Organization\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Organization\Application\Structure\PositionInput;

/**
 * A position. A new one takes `legal_entity_id`, `code` (letters, digits, dot, dash, underscore; up to 30), `title`, `unit_id` and optionally `min_grade_id`,
 * `max_grade_id`, `reports_to_position_id` and `job_role_id`; an edit takes any of the fields except the code (null clears an optional one) as a new version. Both take
 * `valid_from` and `reason`.
 */
final class PositionRequest extends FormRequest
{
    use DatedChangeFields;

    private const array PROPERTY_BY_FIELD = [
        'title' => 'title', 'unit_id' => 'unitId', 'min_grade_id' => 'minGradeId', 'max_grade_id' => 'maxGradeId', 'reports_to_position_id' => 'reportsToPositionId', 'job_role_id' => 'jobRoleId',
    ];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $new = $this->isMethod('POST');
        $presence = $new ? 'required' : 'sometimes';

        return [
            'legal_entity_id' => [...($new ? ['required'] : ['prohibited']), 'integer', 'min:1'],
            'code' => [...($new ? ['required'] : ['prohibited']), 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
            'title' => [$presence, 'string', 'min:2', 'max:150'],
            'unit_id' => [$presence, 'integer', 'min:1'],
            'min_grade_id' => ['nullable', 'integer', 'min:1'],
            'max_grade_id' => ['nullable', 'integer', 'min:1'],
            'reports_to_position_id' => ['nullable', 'integer', 'min:1'],
            'job_role_id' => ['nullable', 'integer', 'min:1'],
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

    public function position(): PositionInput
    {
        $id = fn (string $field): ?int => $this->filled($field) ? $this->integer($field) : null;

        return new PositionInput(
            trim($this->string('title')->toString()), $this->integer('unit_id'), $id('min_grade_id'), $id('max_grade_id'), $id('reports_to_position_id'), $id('job_role_id'),
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
                $changes[$property] = $field === 'title' ? trim((string) $value) : ($value === null ? null : (int) $value);
            }
        }

        return $changes;
    }
}
