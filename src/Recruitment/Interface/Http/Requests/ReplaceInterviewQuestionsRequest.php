<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The whole list of questions an interview keeps; an empty list clears it. */
final class ReplaceInterviewQuestionsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return KitQuestionFields::rules(mayBeEmpty: true);
    }

    /** @return list<array{text: string, guidance: ?string, weight: int, anchor_1?: ?string, anchor_3?: ?string, anchor_5?: ?string}> */
    public function questions(): array
    {
        return KitQuestionFields::questions($this->array('questions'));
    }
}
