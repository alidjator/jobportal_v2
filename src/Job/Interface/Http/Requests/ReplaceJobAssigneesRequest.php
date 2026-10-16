<?php

namespace Src\Job\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Job\Domain\JobAssignmentRole;

/**
 * The whole set that works on the job: `assignees` (`user_id` and a `role`, recruiter by default) or, as before, the plain `user_ids`, where an assignee
 * that stays keeps the role it has. Whether they belong to the company is checked by the use case.
 */
final class ReplaceJobAssigneesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_ids' => ['required_without:assignees', 'prohibits:assignees', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'min:1', 'distinct'],
            'assignees' => ['required_without:user_ids', 'array', 'min:1', 'max:100'],
            'assignees.*.user_id' => ['required', 'integer', 'min:1', 'distinct'],
            'assignees.*.role' => ['nullable', Rule::enum(JobAssignmentRole::class)],
        ];
    }

    /** @return array<int, ?JobAssignmentRole> the wanted role by account id; null keeps the current one */
    public function assignees(): array
    {
        $wanted = [];

        if ($this->has('assignees')) {
            foreach ($this->array('assignees') as $row) {
                $role = is_array($row) && isset($row['role']) ? JobAssignmentRole::from((string) $row['role']) : JobAssignmentRole::Recruiter;
                $wanted[(int) (is_array($row) ? $row['user_id'] : 0)] = $role;
            }

            return $wanted;
        }

        foreach ($this->array('user_ids') as $userId) {
            $wanted[(int) $userId] = null;
        }

        return $wanted;
    }
}
