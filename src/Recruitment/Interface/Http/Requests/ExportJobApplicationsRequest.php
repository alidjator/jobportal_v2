<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Application\RecruiterApplicationQuery;
use Src\Recruitment\Domain\StageType;

/** The filters and the order of the pipeline list (without paging): `filter[stage_id]`, `filter[stage_type]`, `filter[meets_must_have]`, `filter[q]` and `sort`. Any other filter is refused with 422. */
final class ExportJobApplicationsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:stage_id,stage_type,meets_must_have,q'],
            'filter.stage_id' => ['nullable', 'integer', 'min:1'],
            'filter.stage_type' => ['nullable', 'string', Rule::enum(StageType::class)],
            'filter.meets_must_have' => ['nullable', 'in:true,false,1,0'],
            'filter.q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:applied_at,-applied_at'],
        ];
    }

    public function toQuery(int $jobId): RecruiterApplicationQuery
    {
        return new RecruiterApplicationQuery(
            $jobId,
            $this->filled('filter.stage_id') ? $this->integer('filter.stage_id') : null,
            $this->filled('filter.stage_type') ? StageType::from($this->string('filter.stage_type')->toString()) : null,
            $this->filled('filter.meets_must_have') ? $this->boolean('filter.meets_must_have') : null,
            $this->filled('filter.q') ? trim($this->string('filter.q')->toString()) : null,
            $this->input('sort') !== 'applied_at',
        );
    }
}
