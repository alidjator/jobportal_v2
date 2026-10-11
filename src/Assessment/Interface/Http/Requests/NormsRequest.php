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
            'norms.*' => ['array:scale_key,raw_min,raw_max,standard_score,band'],
            'norms.*.scale_key' => ['required', 'string', 'max:100'],
            'norms.*.raw_min' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'norms.*.raw_max' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'norms.*.standard_score' => ['required', 'numeric', 'min:-99999', 'max:99999'],
            'norms.*.band' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return list<Norm> */
    public function norms(): array
    {
        return array_values(array_map(
            static fn (array $row): Norm => new Norm((string) $row['scale_key'], (int) $row['raw_min'], (int) $row['raw_max'], (float) $row['standard_score'], (string) $row['band']),
            $this->array('norms'),
        ));
    }
}
