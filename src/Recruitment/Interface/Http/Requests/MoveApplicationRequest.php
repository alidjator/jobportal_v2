<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The stage to move an application to. Whether it is an active stage of the company is a use case rule. */
final class MoveApplicationRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['stage_id' => ['required', 'integer', 'min:1']];
    }

    public function stageId(): int
    {
        return $this->integer('stage_id');
    }
}
