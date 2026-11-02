<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The number of a report as printed on it: `CODE/YEAR/SEQUENCE`, with `-R1`, `-R2` for a revision. */
final class VerifyReportRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9]{2,10}\/\d{4}\/\d{5,}(-[Rr]\d+)?$/']];
    }

    public function number(): string
    {
        return $this->string('number')->toString();
    }
}
