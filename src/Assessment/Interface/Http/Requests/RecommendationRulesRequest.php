<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The rule of a battery that works out the recommendation of its automatically issued reports: the key scales and two standard-score thresholds. The scales are checked by the use case. */
final class RecommendationRulesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'key_scales' => ['required', 'array', 'min:1', 'max:50'],
            'key_scales.*' => ['required', 'string', 'max:100'],
            /** Every key scale at or above this standard score: recommended. */
            'recommended_min' => ['required', 'numeric', 'min:-9999.99', 'max:9999.99'],
            /** Any key scale below this standard score: not yet recommended. Not above `recommended_min`. */
            'not_yet_below' => ['required', 'numeric', 'min:-9999.99', 'max:9999.99'],
        ];
    }

    /** @return list<string> */
    public function keyScales(): array
    {
        return array_values(array_map('strval', $this->array('key_scales')));
    }

    public function recommendedMin(): float
    {
        return (float) $this->input('recommended_min');
    }

    public function notYetBelow(): float
    {
        return (float) $this->input('not_yet_below');
    }
}
