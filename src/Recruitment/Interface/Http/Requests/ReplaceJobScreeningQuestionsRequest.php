<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The whole ordered list of questions of a job. The shape only: what a question may be asked, and the ideal answers, are checked by the use case. */
final class ReplaceJobScreeningQuestionsRequest extends FormRequest
{
    use ScreeningItemsInput;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return $this->itemRules();
    }
}
