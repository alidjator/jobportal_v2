<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Allowed: `filter[from]` and `filter[to]` (both needed for the file, at most 366 days), `filter[psychologist_id]` and `filter[test_id]`. */
final class ExportRegisterRequest extends FormRequest
{
    use ReadsRegisterFilter;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->registerFilterRules();
    }
}
