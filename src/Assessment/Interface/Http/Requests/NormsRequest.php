<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Assessment\Domain\Norm;

/** The whole norm table of a battery. The ranges and scales are checked by the use case. */
final class NormsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'norms' => ['present', 'array', 'max:500'],
            'norms.*' => ['array:scale_key,raw_min,raw_max,standard_score,band,explanation,age_min,age_max,education_rank_min,education_rank_max'],
            'norms.*.scale_key' => ['required', 'string', 'max:100'],
            'norms.*.raw_min' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'norms.*.raw_max' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'norms.*.standard_score' => ['required', 'numeric', 'min:-99999', 'max:99999'],
            'norms.*.band' => ['required', 'string', 'max:100'],
            'norms.*.explanation' => ['nullable', 'string', 'max:2000'],
            /** The group the row holds for: whole years, and the education ranks of the candidate search; leave a field empty for no limit. A row without any applies to everyone. */
            'norms.*.age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'norms.*.age_max' => ['nullable', 'integer', 'min:0', 'max:120'],
            'norms.*.education_rank_min' => ['nullable', 'integer', 'min:0', 'max:100'],
            'norms.*.education_rank_max' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /** @return list<Norm> */
    public function norms(): array
    {
        return array_values(array_map(
            static fn (array $row): Norm => new Norm((string) $row['scale_key'], (int) $row['raw_min'], (int) $row['raw_max'], (float) $row['standard_score'], (string) $row['band'], isset($row['explanation']) ? (string) $row['explanation'] : null,
                isset($row['age_min']) ? (int) $row['age_min'] : null, isset($row['age_max']) ? (int) $row['age_max'] : null,
                isset($row['education_rank_min']) ? (int) $row['education_rank_min'] : null, isset($row['education_rank_max']) ? (int) $row['education_rank_max'] : null,
            ),
            $this->array('norms'),
        ));
    }
}
