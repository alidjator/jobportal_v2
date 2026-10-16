<?php

namespace Src\Workforce\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Workforce\Application\Requisition\RequisitionInput;
use Src\Workforce\Domain\RequisitionType;

/**
 * A requisition (`POST` creates a draft, `PUT` replaces what a draft or rejected one says): `type`, `position_id`, `hires`, `job_type_id`, `target_join_date`, `justification`,
 * `hiring_manager_user_id`; optional `requested_grade_id`, `contract_months` (a contract needs it), `cost_centre_id` (the unit's by default), `salary_min`, `salary_max` and, for a
 * replacement, `replacement_name` and `replacement_leaving_date`. Whether the ids fit the position and the tenant is checked by the use case.
 */
final class SaveRequisitionRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(RequisitionType::class)],
            'position_id' => ['required', 'integer', 'min:1'],
            'requested_grade_id' => ['nullable', 'integer', 'min:1'],
            'hires' => ['required', 'integer', 'min:1', 'max:500'],
            'job_type_id' => ['required', 'integer', 'min:1'],
            'contract_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'target_join_date' => ['required', 'date_format:Y-m-d'],
            'cost_centre_id' => ['nullable', 'integer', 'min:1'],
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'justification' => ['required', 'string', 'min:10', 'max:2000'],
            'replacement_name' => ['nullable', 'string', 'max:150'],
            'replacement_leaving_date' => ['nullable', 'date_format:Y-m-d'],
            'hiring_manager_user_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function requisition(): RequisitionInput
    {
        $day = fn (string $key): ?DateTimeImmutable => $this->filled($key) ? new DateTimeImmutable($this->string($key)->toString()) : null;
        $number = fn (string $key): ?int => $this->filled($key) ? $this->integer($key) : null;

        return new RequisitionInput(
            RequisitionType::from($this->string('type')->toString()), $this->integer('position_id'), $number('requested_grade_id'), $this->integer('hires'), $this->integer('job_type_id'),
            $number('contract_months'), new DateTimeImmutable($this->string('target_join_date')->toString()), $number('cost_centre_id'), $number('salary_min'), $number('salary_max'),
            $this->string('justification')->toString(), $this->filled('replacement_name') ? $this->string('replacement_name')->toString() : null, $day('replacement_leaving_date'),
            $this->integer('hiring_manager_user_id'),
        );
    }
}
