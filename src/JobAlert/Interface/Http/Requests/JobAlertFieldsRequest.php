<?php

namespace Src\JobAlert\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\JobAlert\Application\JobAlertInput;
use Src\JobAlert\Domain\AlertCriteria;
use Src\JobAlert\Domain\AlertFrequency;
use Src\JobAlert\Domain\JobAlert;

/**
 * The body that creates or replaces a job alert: `name`, `frequency` (`immediate`, `daily`, `weekly`), `criteria` (the filters of the public job search by name:
 * `q`, `work_mode`, the id filters as lists of ids, `salary_min` with `salary_period_id`) and the channels `email` and `in_app` (both on by default). Only the shape
 * is checked here; the rules (at least one filter, a period with the salary, at least one channel) are the domain's. Any other criterion is refused with 422.
 */
final class JobAlertFieldsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:'.JobAlert::MAX_NAME_LENGTH],
            'frequency' => ['required', 'string', Rule::enum(AlertFrequency::class)],
            'criteria' => ['required', 'array:'.implode(',', ['q', 'work_mode', 'salary_min', 'salary_period_id', ...AlertCriteria::ID_FILTERS])],
            'criteria.q' => ['nullable', 'string', 'max:'.AlertCriteria::MAX_KEYWORD_LENGTH],
            'criteria.work_mode' => ['nullable', 'array', 'max:'.AlertCriteria::MAX_VALUES],
            'criteria.work_mode.*' => ['string', Rule::in(AlertCriteria::WORK_MODES)],
            'criteria.salary_min' => ['nullable', 'integer', 'min:0', 'max:'.AlertCriteria::MAX_SALARY],
            'criteria.salary_period_id' => ['nullable', 'integer', 'min:1'],
            'email' => ['sometimes', 'boolean'],
            'in_app' => ['sometimes', 'boolean'],
        ];

        foreach (AlertCriteria::ID_FILTERS as $filter) {
            $rules["criteria.{$filter}"] = ['nullable', 'array', 'max:'.AlertCriteria::MAX_VALUES];
            $rules["criteria.{$filter}.*"] = ['integer', 'min:1'];
        }

        return $rules;
    }

    public function toInput(): JobAlertInput
    {
        /** @var array<string, mixed> $criteria */
        $criteria = $this->validated('criteria');

        foreach (AlertCriteria::ID_FILTERS as $filter) {
            if (is_array($criteria[$filter] ?? null)) {
                $criteria[$filter] = array_map('intval', $criteria[$filter]);
            }
        }

        foreach (['salary_min', 'salary_period_id'] as $number) {
            if (isset($criteria[$number])) {
                $criteria[$number] = (int) $criteria[$number];
            }
        }

        return new JobAlertInput(
            $this->string('name')->toString(),
            AlertFrequency::from($this->string('frequency')->toString()),
            $criteria,
            $this->boolean('email', true),
            $this->boolean('in_app', true),
        );
    }
}
