<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Assessment\Application\Delivery\InvitationChecks;

/**
 * The default test of a job for an assessment stage: the test, the days to complete it (default 3), the assessors and, for a skill test, the rule that moves an application on
 * by its score (`advance`: the percentage to reach and the stage to move to). Whether the test, the people and the stages are acceptable is a use case rule.
 */
final class SetJobDefaultRequest extends FormRequest
{
    private const int DEFAULT_DUE_DAYS = 3;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'test_id' => ['required', 'integer', 'min:1'],
            'due_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'assessor_ids' => ['sometimes', 'array', 'max:'.InvitationChecks::MAX_ASSESSORS],
            'assessor_ids.*' => ['integer', 'min:1', 'distinct'],
            'advance' => ['sometimes', 'nullable', 'array:min_percentage,to_stage_id'],
            'advance.min_percentage' => ['required_with:advance', 'integer', 'between:1,100'],
            'advance.to_stage_id' => ['required_with:advance', 'integer', 'min:1'],
        ];
    }

    public function testId(): int
    {
        return $this->integer('test_id');
    }

    public function dueDays(): int
    {
        return $this->has('due_days') ? $this->integer('due_days') : self::DEFAULT_DUE_DAYS;
    }

    /** @return list<int> */
    public function assessorIds(): array
    {
        return array_map('intval', (array) $this->input('assessor_ids', []));
    }

    public function advanceMinPercentage(): ?int
    {
        return $this->filled('advance') ? $this->integer('advance.min_percentage') : null;
    }

    public function advanceToStageId(): ?int
    {
        return $this->filled('advance') ? $this->integer('advance.to_stage_id') : null;
    }
}
