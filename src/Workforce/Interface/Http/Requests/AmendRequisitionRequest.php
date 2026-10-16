<?php

namespace Src\Workforce\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Workforce\Application\Requisition\AmendmentInput;

/** An amendment of an open requisition: the new `hires` and/or `salary_min` and `salary_max` (what is left out stays), and the `reason`. */
final class AmendRequisitionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'hires' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'salary_min' => ['sometimes', 'integer', 'min:0', 'max:9999999999'],
            'salary_max' => ['sometimes', 'integer', 'min:0', 'max:9999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function amendment(): AmendmentInput
    {
        $number = fn (string $key): ?int => $this->has($key) ? $this->integer($key) : null;

        return new AmendmentInput($number('hires'), $number('salary_min'), $number('salary_max'), trim($this->string('reason')->toString()));
    }
}
