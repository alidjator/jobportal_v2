<?php

namespace Src\Workforce\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Workforce\Application\Plan\PlanLineInput;
use Src\Workforce\Application\Plan\PlanLineRules;

/**
 * A plan's lines, and for a new plan its `fiscal_year` (the calendar year the fiscal year starts in) and, for a group member, the `legal_entity_id`. A line has the
 * `position_id`, `opening_headcount`, `target_headcount`, the twelve `months` of additions and an optional `estimated_annual_cost`.
 */
final class SavePlanRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'fiscal_year' => [$creating ? 'required' : 'prohibited', 'integer', 'between:2000,2100'],
            'legal_entity_id' => [$creating ? 'sometimes' : 'prohibited', 'integer', 'min:1'],
            'lines' => [$creating ? 'sometimes' : 'present', 'array', 'max:'.PlanLineRules::MAX_LINES],
            'lines.*.position_id' => ['required', 'integer', 'min:1'],
            'lines.*.opening_headcount' => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.target_headcount' => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.months' => ['required', 'array', 'size:12'],
            'lines.*.months.*' => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.estimated_annual_cost' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function fiscalYear(): int
    {
        return $this->integer('fiscal_year');
    }

    public function legalEntityId(): ?int
    {
        return $this->has('legal_entity_id') ? $this->integer('legal_entity_id') : null;
    }

    /** @return list<PlanLineInput> */
    public function lines(): array
    {
        return array_map(static fn (array $line): PlanLineInput => new PlanLineInput(
            (int) $line['position_id'], (int) $line['opening_headcount'], (int) $line['target_headcount'], array_map('intval', array_values($line['months'])),
            isset($line['estimated_annual_cost']) ? (int) $line['estimated_annual_cost'] : null,
        ), array_values($this->input('lines', [])));
    }
}
