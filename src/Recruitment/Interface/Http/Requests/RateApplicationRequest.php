<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The stars; the range is a domain rule (422 `invalid-rating`). */
final class RateApplicationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['stars' => ['required', 'integer']];
    }

    public function stars(): int
    {
        return $this->integer('stars');
    }
}
