<?php

namespace Src\Job\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Job\Application\Insight\SalaryInsightQuery;

/** `role_id` is required; `province_id`, `city_id`, `experience_level_id` and `category_id` are optional ids. Whether an id exists is checked by the use case. */
final class SalaryInsightRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'min:1'],
            'province_id' => ['nullable', 'integer', 'min:1'],
            'city_id' => ['nullable', 'integer', 'min:1'],
            'experience_level_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function insightQuery(): SalaryInsightQuery
    {
        return new SalaryInsightQuery(
            $this->integer('role_id'),
            $this->id('province_id'),
            $this->id('city_id'),
            $this->id('experience_level_id'),
            $this->id('category_id'),
        );
    }

    private function id(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
