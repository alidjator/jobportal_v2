<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Application\OfferInput;

/** The position, monthly salary, start date, note and deadline of an offer. */
final class SendOfferRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return OfferFields::rules();
    }

    public function toInput(): OfferInput
    {
        return OfferFields::input($this->validated());
    }
}
