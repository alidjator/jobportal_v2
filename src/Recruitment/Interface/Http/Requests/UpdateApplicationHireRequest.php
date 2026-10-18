<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Application\HireChange;

/** The parts of the hire details to change; a part that is missing stays as it is, and a null note clears it (a null join date is refused by the use case). */
final class UpdateApplicationHireRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return HireFields::rules();
    }

    public function change(): HireChange
    {
        $input = HireFields::input($this->validated());

        return new HireChange($this->has('join_date'), $input->joinDate, $this->has('note'), $input->note);
    }
}
