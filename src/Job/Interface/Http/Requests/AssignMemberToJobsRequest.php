<?php

namespace Src\Job\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The jobs to add the member to: 1 to 100 ids; a repeated id is allowed and collapses. Whether they belong to the company is checked by the use case. */
final class AssignMemberToJobsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'job_ids' => ['required', 'array', 'min:1', 'max:100'],
            'job_ids.*' => ['integer', 'min:1'],
        ];
    }

    /** @return list<int> */
    public function jobIds(): array
    {
        return array_values(array_map(static fn (mixed $id): int => (int) $id, $this->array('job_ids')));
    }
}
