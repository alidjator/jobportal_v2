<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The name and the whole list of questions of a kit, for a new kit and for a replacement alike. */
final class SaveInterviewKitRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string'], ...KitQuestionFields::rules(mayBeEmpty: false)];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    /** @return list<array{text: string, guidance: ?string, weight: int, anchor_1?: ?string, anchor_3?: ?string, anchor_5?: ?string}> */
    public function questions(): array
    {
        return KitQuestionFields::questions($this->array('questions'));
    }
}
